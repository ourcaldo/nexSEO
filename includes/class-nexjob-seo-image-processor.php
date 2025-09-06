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
     * Draw large, centered text that's proportional to image size and title length
     */
    private function draw_centered_text($image, $text, $color, $shadow_color, $width, $height) {
        // Calculate intelligent font size based on image dimensions AND title length
        $title_length = strlen($text);
        
        // Base font size calculation - much larger than before
        $base_font_size = min($width, $height) / 15; // Changed from /200 to /15 for readable text
        
        // Adjust font size based on title length for optimal readability
        if ($title_length <= 20) {
            // Short titles: use largest font
            $font_size = $base_font_size * 1.2;
        } elseif ($title_length <= 40) {
            // Medium titles: use normal font
            $font_size = $base_font_size;
        } elseif ($title_length <= 60) {
            // Long titles: use smaller font
            $font_size = $base_font_size * 0.8;
        } else {
            // Very long titles: use smallest font
            $font_size = $base_font_size * 0.6;
        }
        
        // Ensure minimum readable size
        $font_size = max(16, $font_size);
        
        // Maximum usable width (90% of image width)
        $max_text_width = $width * 0.9;
        
        // Wrap text intelligently based on available space
        $lines = $this->wrap_text_to_fit($text, $font_size, $max_text_width);
        
        // Limit to 3 lines maximum
        if (count($lines) > 3) {
            $lines = array_slice($lines, 0, 3);
            // Add ellipsis to last line if text was truncated
            $last_line = $lines[2];
            if (strlen($last_line) > 5) {
                $lines[2] = substr($last_line, 0, -3) . '...';
            }
        }
        
        // Calculate line height (font size + spacing)
        $line_height = $font_size * 1.3;
        $total_text_height = count($lines) * $line_height;
        
        // Calculate vertical center position
        $start_y = ($height - $total_text_height) / 2;
        
        // Draw each line centered
        foreach ($lines as $line_index => $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            // Calculate line width for centering
            $line_width = $this->calculate_text_width($line, $font_size);
            
            // Calculate horizontal center position
            $x = ($width - $line_width) / 2;
            $y = $start_y + ($line_index * $line_height);
            
            // Draw text with shadow for maximum visibility
            $this->draw_text_with_shadow($image, $x, $y, $line, $font_size, $color, $shadow_color);
        }
    }
    
    /**
     * Draw text with shadow using proper font sizing
     */
    private function draw_text_with_shadow($image, $x, $y, $text, $font_size, $color, $shadow_color) {
        // Use imagestring for consistent rendering with calculated size
        $gd_font = 5; // Largest built-in GD font
        
        // Calculate how many times to draw for thickness based on font size
        $thickness = max(1, intval($font_size / 20));
        
        // Draw shadow first (offset by thickness + 1)
        $shadow_offset = $thickness + 1;
        for ($i = 0; $i < $thickness; $i++) {
            imagestring($image, $gd_font, $x + $shadow_offset + $i, $y + $shadow_offset + $i, $text, $shadow_color);
        }
        
        // Draw main text with thickness for bold effect
        for ($i = 0; $i < $thickness; $i++) {
            for ($j = 0; $j < $thickness; $j++) {
                imagestring($image, $gd_font, $x + $i, $y + $j, $text, $color);
            }
        }
    }
    
    /**
     * Calculate text width for centering
     */
    private function calculate_text_width($text, $font_size) {
        // Base character width for GD font 5
        $char_width = 9; // Average width of GD font 5 characters
        
        // Scale based on our font size calculation
        $scale_factor = $font_size / 20; // Base scaling
        
        return strlen($text) * $char_width * $scale_factor;
    }
    
    /**
     * Wrap text to fit within specified width
     */
    private function wrap_text_to_fit($text, $font_size, $max_width) {
        $char_width = 9 * ($font_size / 20); // Scaled character width
        $chars_per_line = max(10, floor($max_width / $char_width));
        
        // Use wordwrap to break text intelligently
        $wrapped = wordwrap($text, $chars_per_line, "\n", true);
        return explode("\n", $wrapped);
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