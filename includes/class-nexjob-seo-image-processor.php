<?php
/**
 * Image Processing Service
 * 
 * Handles low-level image manipulation, text rendering, and quality optimization
 */

class NexJob_SEO_Image_Processor {
    private $settings;
    private $logger;
    private $temp_dir;

    public function __construct($settings, $logger) {
        $this->settings = $settings;
        $this->logger = $logger;
        $this->temp_dir = sys_get_temp_dir() . '/nexjob-seo-images/';
        
        $this->ensure_temp_directory();
        $this->check_image_support();
    }

    /**
     * Ensure temporary directory exists
     */
    private function ensure_temp_directory() {
        if (!file_exists($this->temp_dir)) {
            wp_mkdir_p($this->temp_dir);
        }
    }

    /**
     * Check for image processing support
     */
    private function check_image_support() {
        if (!extension_loaded('gd')) {
            $this->logger->log('GD extension not available for image processing', 'error');
        }
    }

    /**
     * Generate image with text overlay
     */
    public function generate_image_with_text($template_path, $text, $config) {
        try {
            if (!file_exists($template_path)) {
                throw new Exception("Template file not found: {$template_path}");
            }

            // Load template image
            $template_image = $this->load_image($template_path);
            if (!$template_image) {
                throw new Exception("Failed to load template image");
            }

            // Apply text overlay
            $result_image = $this->add_text_overlay($template_image, $text, $config);
            if (!$result_image) {
                imagedestroy($template_image);
                throw new Exception("Failed to add text overlay");
            }

            // Generate output filename
            $output_file = $this->temp_dir . 'generated-' . uniqid() . '.png';
            
            // Save image
            if (!imagepng($result_image, $output_file, 9)) {
                imagedestroy($result_image);
                throw new Exception("Failed to save generated image");
            }

            // Clean up
            imagedestroy($result_image);

            return $output_file;

        } catch (Exception $e) {
            $this->logger->log("Image processing error: " . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Load image from file
     */
    private function load_image($file_path) {
        $image_info = getimagesize($file_path);
        if (!$image_info) {
            return false;
        }

        switch ($image_info[2]) {
            case IMAGETYPE_JPEG:
                return imagecreatefromjpeg($file_path);
            case IMAGETYPE_PNG:
                return imagecreatefrompng($file_path);
            case IMAGETYPE_GIF:
                return imagecreatefromgif($file_path);
            default:
                return false;
        }
    }

    /**
     * Add text overlay to image - simplified and centered
     */
    private function add_text_overlay($image, $text, $config) {
        $width = imagesx($image);
        $height = imagesy($image);

        // Create a copy of the image to work with
        $result_image = imagecreatetruecolor($width, $height);
        imagecopy($result_image, $image, 0, 0, 0, 0, $width, $height);

        // Get font color
        $font_color = isset($config['font_color']) ? $config['font_color'] : '#FFFFFF';
        
        // Convert hex color to RGB
        $color = $this->hex_to_rgb($font_color);
        $text_color = imagecolorallocate($result_image, $color['r'], $color['g'], $color['b']);
        $shadow_color = imagecolorallocate($result_image, 0, 0, 0); // Black shadow

        // Draw centered text with proportional size
        $this->draw_centered_text($result_image, $text, $text_color, $shadow_color, $width, $height);

        return $result_image;
    }

    /**
     * Draw large, centered text that's proportional to image size
     */
    private function draw_centered_text($image, $text, $color, $shadow_color, $width, $height) {
        // Calculate proportional font scale based on image size
        // For a 1200x800 image, we want decent sized text
        $base_scale = min($width, $height) / 200; // Scale factor based on smaller dimension
        $font_scale = max(3, intval($base_scale)); // Minimum scale of 3, proportional to image size
        
        // Use largest built-in GD font
        $gd_font = 5;
        
        // Calculate character dimensions
        $base_char_width = imagefontwidth($gd_font);
        $base_char_height = imagefontheight($gd_font);
        
        // Effective dimensions with scaling  
        $char_width = $base_char_width * $font_scale;
        $char_height = $base_char_height * $font_scale;
        
        // Word wrap text to fit 80% of image width
        $usable_width = $width * 0.8;
        $chars_per_line = max(8, floor($usable_width / $char_width));
        $wrapped_text = wordwrap($text, $chars_per_line, "\n", true);
        $lines = explode("\n", $wrapped_text);
        
        // Limit to 3 lines maximum
        if (count($lines) > 3) {
            $lines = array_slice($lines, 0, 3);
            $lines[2] = rtrim($lines[2]) . '...';
        }
        
        // Calculate total text block height
        $line_spacing = $char_height * 1.2;
        $total_text_height = count($lines) * $line_spacing;
        
        // Center vertically
        $start_y = ($height - $total_text_height) / 2;
        
        // Draw each line centered
        foreach ($lines as $line_num => $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            $line_width = strlen($line) * $char_width;
            
            // Center horizontally
            $x = ($width - $line_width) / 2;
            $y = $start_y + ($line_num * $line_spacing);
            
            // Draw text with shadow for visibility
            $this->draw_scaled_text($image, $gd_font, $x, $y, $line, $color, $shadow_color, $font_scale);
        }
    }
    
    /**
     * Draw scaled text with shadow for maximum visibility
     */
    private function draw_scaled_text($image, $font, $x, $y, $text, $color, $shadow_color, $scale) {
        // Draw shadow with bigger offset for larger text
        $shadow_offset = max(2, $scale);
        for ($sx = 0; $sx < $scale; $sx++) {
            for ($sy = 0; $sy < $scale; $sy++) {
                imagestring($image, $font, $x + $sx + $shadow_offset, $y + $sy + $shadow_offset, $text, $shadow_color);
            }
        }
        
        // Draw main text with scaling for thickness
        for ($sx = 0; $sx < $scale; $sx++) {
            for ($sy = 0; $sy < $scale; $sy++) {
                imagestring($image, $font, $x + $sx, $y + $sy, $text, $color);
            }
        }
    }

    /**
     * Convert hex color to RGB array
     */
    private function hex_to_rgb($hex) {
        $hex = ltrim($hex, '#');
        
        if (strlen($hex) == 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        
        return array(
            'r' => hexdec(substr($hex, 0, 2)),
            'g' => hexdec(substr($hex, 2, 2)),
            'b' => hexdec(substr($hex, 4, 2))
        );
    }

    /**
     * Optimize image quality - keep original dimensions
     */
    public function optimize_image($image_path) {
        if (!file_exists($image_path)) {
            return false;
        }

        $image = $this->load_image($image_path);
        if (!$image) {
            return false;
        }

        // Keep original dimensions - don't resize
        // Just save with compression
        $success = imagepng($image, $image_path, 9);
        
        imagedestroy($image);
        
        return $success;
    }

    /**
     * Clean up temporary files
     */
    public function cleanup_temp_files($older_than_hours = 24) {
        $files = glob($this->temp_dir . '*');
        $cutoff_time = time() - ($older_than_hours * 3600);
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoff_time) {
                unlink($file);
            }
        }
    }

    /**
     * Get supported image formats
     */
    public function get_supported_formats() {
        $formats = array();
        
        if (function_exists('imagecreatefromjpeg')) {
            $formats[] = 'jpeg';
            $formats[] = 'jpg';
        }
        
        if (function_exists('imagecreatefrompng')) {
            $formats[] = 'png';
        }
        
        if (function_exists('imagecreatefromgif')) {
            $formats[] = 'gif';
        }
        
        return $formats;
    }
}