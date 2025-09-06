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
     * Add text overlay to image
     */
    private function add_text_overlay($image, $text, $config) {
        $width = imagesx($image);
        $height = imagesy($image);

        // Create a copy of the image to work with
        $result_image = imagecreatetruecolor($width, $height);
        imagecopy($result_image, $image, 0, 0, 0, 0, $width, $height);

        // Parse text configuration
        $text_area = isset($config['text_area']) ? $config['text_area'] : array(
            'x' => 50,
            'y' => 100,
            'width' => $width - 100,
            'height' => $height - 200
        );

        $font_size = isset($config['font_size']) ? $config['font_size'] : 48;
        $font_color = isset($config['font_color']) ? $config['font_color'] : '#FFFFFF';
        $line_height = isset($config['line_height']) ? $config['line_height'] : 1.2;
        $text_align = isset($config['text_align']) ? $config['text_align'] : 'center';

        // Convert hex color to RGB
        $color = $this->hex_to_rgb($font_color);
        $text_color = imagecolorallocate($result_image, $color['r'], $color['g'], $color['b']);

        // Use built-in font (imagestring) for simplicity
        // In a production environment, you might want to use TTF fonts with imagettftext
        $this->draw_text_simple($result_image, $text, $text_area, $text_color, $font_size, $text_align);

        return $result_image;
    }

    /**
     * Draw text using simple built-in fonts
     */
    private function draw_text_simple($image, $text, $text_area, $color, $font_size, $align) {
        // Map font size to built-in font numbers (1-5)
        $gd_font = min(5, max(1, intval($font_size / 12)));
        
        // Calculate character dimensions
        $char_width = imagefontwidth($gd_font);
        $char_height = imagefontheight($gd_font);
        
        // Calculate characters per line
        $chars_per_line = floor($text_area['width'] / $char_width);
        
        // Word wrap text
        $wrapped_text = wordwrap($text, $chars_per_line, "\n", true);
        $lines = explode("\n", $wrapped_text);
        
        // Calculate starting position
        $total_text_height = count($lines) * $char_height;
        $start_y = $text_area['y'] + ($text_area['height'] - $total_text_height) / 2;
        
        // Draw each line
        foreach ($lines as $line_num => $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            $line_width = strlen($line) * $char_width;
            
            // Calculate x position based on alignment
            switch ($align) {
                case 'center':
                    $x = $text_area['x'] + ($text_area['width'] - $line_width) / 2;
                    break;
                case 'right':
                    $x = $text_area['x'] + $text_area['width'] - $line_width;
                    break;
                case 'left':
                default:
                    $x = $text_area['x'];
                    break;
            }
            
            $y = $start_y + ($line_num * $char_height);
            
            // Draw the line
            imagestring($image, $gd_font, $x, $y, $line, $color);
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
     * Optimize image quality and size
     */
    public function optimize_image($image_path) {
        if (!file_exists($image_path)) {
            return false;
        }

        $image = $this->load_image($image_path);
        if (!$image) {
            return false;
        }

        // Get target dimensions
        $target_width = $this->settings->get('featured_image_width', 1200);
        $target_height = $this->settings->get('featured_image_height', 630);
        
        $current_width = imagesx($image);
        $current_height = imagesy($image);

        // Resize if needed
        if ($current_width != $target_width || $current_height != $target_height) {
            $resized_image = imagecreatetruecolor($target_width, $target_height);
            
            // Preserve transparency for PNG
            imagesavealpha($resized_image, true);
            $transparent = imagecolorallocatealpha($resized_image, 0, 0, 0, 127);
            imagefill($resized_image, 0, 0, $transparent);
            
            // Resize with resampling for better quality
            imagecopyresampled(
                $resized_image, $image,
                0, 0, 0, 0,
                $target_width, $target_height,
                $current_width, $current_height
            );
            
            imagedestroy($image);
            $image = $resized_image;
        }

        // Save optimized image
        $quality = $this->settings->get('featured_image_quality', 85);
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