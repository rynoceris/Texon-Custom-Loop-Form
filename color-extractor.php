<?php
/**
 * Color Extraction Functionality
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Extract dominant colors from an image with support for special formats
 */
function cllf_extract_colors($image_path, $num_colors = 5) {
	// Default colors as fallback
	$default_colors = array('#000000', '#ff0000', '#0000ff', '#00ff00', '#ffff00');
	
	// Get file extension
	$file_extension = strtolower(pathinfo($image_path, PATHINFO_EXTENSION));
	error_log("Extracting colors from file with extension: $file_extension");
	
	// Special handling based on file type
	switch ($file_extension) {
		case 'svg':
			error_log("Using SVG-specific color extraction");
			return cllf_extract_colors_from_svg($image_path, $num_colors);
			
		case 'pdf':
		case 'ai':
		case 'eps':
			error_log("Using PDF/AI/EPS color extraction via ImageMagick");
			return cllf_extract_colors_from_special_format($image_path, $num_colors);
			
		case 'jpg':
		case 'jpeg':
		case 'png':
		case 'gif':
			// Continue with normal GD extraction for raster images
			return cllf_extract_colors_via_gd($image_path, $num_colors);
			
		default:
			error_log("Unknown file extension: $file_extension, using fallback colors");
			return $default_colors;
	}
}

/**
 * Extract colors using GD (traditional method)
 */
function cllf_extract_colors_via_gd($image_path, $num_colors = 5) {
	// Default colors as fallback
	$default_colors = array('#000000', '#ff0000', '#0000ff', '#00ff00', '#ffff00');
	
	error_log("Starting GD color extraction on: $image_path");
	
	// Check if file exists
	if (!file_exists($image_path)) {
		error_log('Image file does not exist: ' . $image_path);
		return $default_colors;
	}
	
	// Check if GD is available
	if (!function_exists('imagecreatefromjpeg')) {
		error_log('GD library is not available');
		return $default_colors;
	}
	
	// Get image info
	$image_info = @getimagesize($image_path);
	if ($image_info === false) {
		error_log('Failed to get image info for: ' . $image_path);
		return $default_colors;
	}
	
	$mime_type = $image_info['mime'];
	$width = $image_info[0];
	$height = $image_info[1];
	
	error_log("Image info: MIME=$mime_type, dimensions={$width}x{$height}");
	
	// Create image resource based on type
	try {
		$image = null;
		
		switch ($mime_type) {
			case 'image/jpeg':
				$image = @imagecreatefromjpeg($image_path);
				break;
			case 'image/png':
				$image = @imagecreatefrompng($image_path);
				break;
			case 'image/gif':
				$image = @imagecreatefromgif($image_path);
				break;
			default:
				error_log('Unsupported image type: ' . $mime_type);
				return $default_colors;
		}
		
		if (!$image) {
			error_log('Failed to create image resource');
			return $default_colors;
		}
		
		// Resize image for faster processing
		$new_width = min($width, 200);
		$new_height = round(($height * $new_width) / $width);
		
		error_log("Resizing to {$new_width}x{$new_height} for processing");
		
		$resized = imagecreatetruecolor($new_width, $new_height);
		if (!$resized) {
			error_log('Failed to create resized image');
			imagedestroy($image);
			return $default_colors;
		}
		
		// Preserve transparency for PNG
		if ($mime_type === 'image/png') {
			imagealphablending($resized, false);
			imagesavealpha($resized, true);
			$transparent = imagecolorallocatealpha($resized, 255, 255, 255, 127);
			imagefilledrectangle($resized, 0, 0, $new_width, $new_height, $transparent);
		}
		
		imagecopyresampled($resized, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
		
		// Sample colors from the image
		$colors = array();
		$color_counts = array();
		$pixels_sampled = 0;
		$pixels_used = 0;
		
		// Sample points in a grid
		$sample_width = max(1, floor($new_width / 15));
		$sample_height = max(1, floor($new_height / 15));
		
		for ($y = 0; $y < $new_height; $y += $sample_height) {
			for ($x = 0; $x < $new_width; $x += $sample_width) {
				$pixels_sampled++;
				
				$rgb = imagecolorat($resized, $x, $y);
				
				// Skip transparent pixels
				$alpha = ($rgb >> 24) & 0x7F;
				if ($alpha >= 120) {
					continue;
				}
				
				$r = ($rgb >> 16) & 0xFF;
				$g = ($rgb >> 8) & 0xFF;
				$b = $rgb & 0xFF;
				
				// Skip very light or very dark colors
				$brightness = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;
				if ($brightness < 30 || $brightness > 230) {
					continue;
				}
				
				// Simplify colors by rounding to nearest 15
				$r = round($r / 15) * 15;
				$g = round($g / 15) * 15;
				$b = round($b / 15) * 15;
				
				$hex = sprintf("#%02x%02x%02x", $r, $g, $b);
				
				if (isset($color_counts[$hex])) {
					$color_counts[$hex]++;
				} else {
					$color_counts[$hex] = 1;
				}
				
				$pixels_used++;
			}
		}
		
		error_log("Sampled $pixels_sampled pixels, used $pixels_used non-transparent/non-extreme pixels");
		
		// If no colors were found, return defaults
		if (empty($color_counts)) {
			error_log('No suitable colors found in the image');
			imagedestroy($image);
			imagedestroy($resized);
			return $default_colors;
		}
		
		// Sort colors by frequency
		arsort($color_counts);
		
		// Log top colors for debugging
		$top_colors = array_slice($color_counts, 0, 10, true);
		error_log("Top 10 colors found: " . print_r($top_colors, true));
		
		// Get top colors
		$result = array_slice(array_keys($color_counts), 0, $num_colors);
		
		// Add black if it's not already in the list
		if (!in_array('#000000', $result)) {
			array_unshift($result, '#000000');
		}
		
		// Clean up
		imagedestroy($image);
		imagedestroy($resized);
		
		error_log("Final extracted colors: " . implode(', ', $result));
		return $result;
	} catch (Exception $e) {
		error_log('Exception in color extraction: ' . $e->getMessage());
		
		if (isset($image) && $image) {
			imagedestroy($image);
		}
		if (isset($resized) && $resized) {
			imagedestroy($resized);
		}
		
		return $default_colors;
	}
}

/**
 * Extract colors from an SVG by parsing the XML
 */
function cllf_extract_colors_from_svg($svg_path, $num_colors = 5) {
	// Default colors as fallback
	$default_colors = array('#000000', '#ff0000', '#0000ff', '#00ff00', '#ffff00');
	
	error_log("Starting SVG color extraction on: $svg_path");
	
	// Check if file exists
	if (!file_exists($svg_path)) {
		error_log("SVG file does not exist: $svg_path");
		return $default_colors;
	}
	
	// Load SVG content
	$svg_content = file_get_contents($svg_path);
	if (!$svg_content) {
		error_log("Failed to read SVG file: $svg_path");
		return $default_colors;
	}
	
	error_log("Successfully read SVG file, size: " . strlen($svg_content) . " bytes");
	
	// Try to convert SVG to PNG using ImageMagick if available
	if (extension_loaded('imagick')) {
		try {
			error_log("Attempting conversion via Imagick extension");
			
			// Create temporary file
			$temp_png = sys_get_temp_dir() . '/' . uniqid('svg_convert_', true) . '.png';
			
			$imagick = new Imagick();
			$imagick->readImageBlob($svg_content);
			$imagick->setImageFormat('png');
			$imagick->resizeImage(300, 300, Imagick::FILTER_LANCZOS, 1, true);
			$imagick->writeImage($temp_png);
			$imagick->clear();
			$imagick->destroy();
			
			if (file_exists($temp_png)) {
				error_log("Converted SVG to PNG via Imagick: $temp_png");
				$colors = cllf_extract_colors_via_gd($temp_png, $num_colors);
				@unlink($temp_png);
				return $colors;
			}
		} catch (Exception $e) {
			error_log("Imagick SVG conversion failed: " . $e->getMessage());
			// Fall through to the parsing approach
		}
	}
	
	// If ImageMagick failed or isn't available, try command-line conversion
	$can_execute = function_exists('exec') && !in_array('exec', array_map('trim', explode(',', ini_get('disable_functions'))));
	if ($can_execute) {
		error_log("Attempting conversion via command-line");
		
		// Create temporary file
		$temp_png = sys_get_temp_dir() . '/' . uniqid('svg_convert_', true) . '.png';
		
		// Convert SVG to PNG using ImageMagick's convert
		$command = "convert -density 300 " . escapeshellarg($svg_path) . " -resize 300x300 " . escapeshellarg($temp_png);
		error_log("Executing command: $command");
		
		exec($command, $output, $return_var);
		
		if ($return_var === 0 && file_exists($temp_png)) {
			error_log("Converted SVG to PNG via command-line: $temp_png");
			$colors = cllf_extract_colors_via_gd($temp_png, $num_colors);
			@unlink($temp_png);
			return $colors;
		} else {
			error_log("Command-line conversion failed: " . implode("\n", $output));
			// Fall through to direct parsing
		}
	}
	
	// Direct XML parsing as a last resort
	error_log("Using direct SVG parsing to extract colors");
	
	// Extract colors using regex
	$colors = array();
	
	// Match fill, stroke, and stop-color attributes with hex values
	$color_patterns = array(
		'fill="(#[0-9a-fA-F]{3,6})"',
		'stroke="(#[0-9a-fA-F]{3,6})"', 
		'stop-color="(#[0-9a-fA-F]{3,6})"',
		'style="[^"]*fill:(#[0-9a-fA-F]{3,6})',
		'style="[^"]*stroke:(#[0-9a-fA-F]{3,6})'
	);
	
	foreach ($color_patterns as $pattern) {
		preg_match_all('/' . $pattern . '/', $svg_content, $matches);
		if (!empty($matches[1])) {
			foreach ($matches[1] as $color) {
				// Normalize color format
				$color = strtolower($color);
				
				// Convert 3-digit hex to 6-digit
				if (strlen($color) === 4) {
					$r = substr($color, 1, 1);
					$g = substr($color, 2, 1);
					$b = substr($color, 3, 1);
					$color = "#{$r}{$r}{$g}{$g}{$b}{$b}";
				}
				
				if (!isset($colors[$color])) {
					$colors[$color] = 1;
				} else {
					$colors[$color]++;
				}
			}
		}
	}
	
	// Also check for RGB values
	preg_match_all('/(?:fill|stroke|stop-color)="rgb\((\d+),\s*(\d+),\s*(\d+)\)/', $svg_content, $rgb_matches);
	if (!empty($rgb_matches[0])) {
		for ($i = 0; $i < count($rgb_matches[0]); $i++) {
			$r = intval($rgb_matches[1][$i]);
			$g = intval($rgb_matches[2][$i]);
			$b = intval($rgb_matches[3][$i]);
			$color = sprintf("#%02x%02x%02x", $r, $g, $b);
			
			if (!isset($colors[$color])) {
				$colors[$color] = 1;
			} else {
				$colors[$color]++;
			}
		}
	}
	
	error_log("Colors found by parsing SVG: " . count($colors));
	
	// If no colors found, return defaults
	if (empty($colors)) {
		error_log("No colors found in SVG, using defaults");
		return $default_colors;
	}
	
	// Sort by frequency
	arsort($colors);
	
	// Get top colors
	$result = array_slice(array_keys($colors), 0, $num_colors);
	
	// Ensure black is included
	if (!in_array('#000000', $result)) {
		array_unshift($result, '#000000');
	}
	
	error_log("Final extracted colors from SVG: " . implode(', ', $result));
	return $result;
}

/**
 * Extract colors from special formats (PDF, AI, EPS) using ImageMagick
 */
function cllf_extract_colors_from_special_format($file_path, $num_colors = 5) {
	// Default colors as fallback
	$default_colors = array('#000000', '#ff0000', '#0000ff', '#00ff00', '#ffff00');
	
	error_log("Starting special format extraction on: $file_path");
	
	// Check if file exists
	if (!file_exists($file_path)) {
		error_log("File does not exist: $file_path");
		return $default_colors;
	}
	
	// First, try to convert using PHP Imagick extension
	if (extension_loaded('imagick')) {
		try {
			error_log("Using PHP Imagick extension for conversion");
			
			// Create temporary file for the converted image
			$temp_png = sys_get_temp_dir() . '/' . uniqid('convert_', true) . '.png';
			
			// Create Imagick instance
			$imagick = new Imagick();
			
			// For PDF, AI, EPS: read only the first page/frame
			$imagick->setResolution(300, 300); // Higher resolution for better color extraction
			$imagick->readImage($file_path . '[0]'); // Read first page/frame
			
			// Convert to PNG
			$imagick->setImageFormat('png');
			
			// Resize to reasonable dimensions for color extraction
			$imagick->resizeImage(300, 300, Imagick::FILTER_LANCZOS, 1, true);
			
			// Write to temporary file
			$imagick->writeImage($temp_png);
			$imagick->clear();
			$imagick->destroy();
			
			error_log("Converted to temporary PNG: $temp_png");
			
			// Now extract colors from the PNG using our regular function
			if (file_exists($temp_png)) {
				// Extract colors normally via GD
				$result = cllf_extract_colors_via_gd($temp_png, $num_colors);
				
				// Clean up
				@unlink($temp_png);
				
				return $result;
			}
		} catch (Exception $e) {
			error_log("Imagick conversion failed: " . $e->getMessage());
			// Fall through to command-line attempt
		}
	}
	
	// If Imagick extension failed or isn't available, try command-line ImageMagick
	$can_execute = function_exists('exec') && !in_array('exec', array_map('trim', explode(',', ini_get('disable_functions'))));
	
	if ($can_execute) {
		error_log("Using command-line ImageMagick for conversion");
		
		// Get temporary file path for the PNG
		$temp_png = sys_get_temp_dir() . '/' . uniqid('convert_', true) . '.png';
		
		// Convert to PNG using ImageMagick's convert
		$command = "convert -density 300 " . escapeshellarg($file_path . '[0]') . " -resize 300x300 " . escapeshellarg($temp_png);
		error_log("Executing command: $command");
		
		exec($command, $output, $return_var);
		
		if ($return_var === 0 && file_exists($temp_png)) {
			error_log("Command-line conversion successful: $temp_png");
			
			// Extract colors from the PNG
			$result = cllf_extract_colors_via_gd($temp_png, $num_colors);
			
			// Clean up
			@unlink($temp_png);
			
			return $result;
		} else {
			error_log("Command-line conversion failed: " . implode("\n", $output));
		}
	}
	
	// If all conversions failed, use default colors
	error_log("All conversion methods failed, using default colors");
	return $default_colors;
}

/**
 * AJAX handler for temporary logo upload
 */
function cllf_temp_logo_upload_ajax() {
	// Verify nonce
	if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'cllf-nonce')) {
		wp_send_json_error('Security check failed');
		exit;
	}
	
	// Check if file was uploaded
	if (!isset($_FILES['logo_file']) || $_FILES['logo_file']['error'] !== UPLOAD_ERR_OK) {
		$error_message = isset($_FILES['logo_file']) ? 
			'Upload error code: ' . $_FILES['logo_file']['error'] : 'No file uploaded';
		error_log('Logo upload error: ' . $error_message);
		wp_send_json_error($error_message);
		exit;
	}
	
	$logo_file = $_FILES['logo_file'];
	$upload_dir = wp_upload_dir();
	$logo_dir = $upload_dir['basedir'] . '/cllf-uploads';
	
	// Log upload information
	error_log('Uploading file: ' . $logo_file['name'] . ', Type: ' . $logo_file['type'] . 
			  ', Size: ' . $logo_file['size']);
	
	// Create upload directory if needed
	if (!file_exists($logo_dir)) {
		if (!mkdir($logo_dir, 0755, true)) {
			error_log('Failed to create upload directory: ' . $logo_dir);
			wp_send_json_error('Failed to create upload directory');
			exit;
		}
	}
	
	// Check if directory is writable
	if (!is_writable($logo_dir)) {
		error_log('Upload directory is not writable: ' . $logo_dir);
		wp_send_json_error('Upload directory is not writable');
		exit;
	}
	
	// Enhanced file type validation
	$file_info = wp_check_filetype($logo_file['name']);
	$file_ext = strtolower($file_info['ext']);
	
	// Define allowed file types
	$allowed_mime_types = array(
		'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/svg+xml',
		'application/pdf', 'application/postscript', 'application/illustrator'
	);
	
	$allowed_extensions = array(
		'jpg', 'jpeg', 'png', 'gif', 'svg', 'pdf', 'ai', 'eps'
	);
	
	error_log('File type check: Provided=' . $logo_file['type'] . ', Detected=' . $file_info['type'] .
			  ', Extension=' . $file_ext);
	
	// Check both MIME type and extension
	if (!in_array($logo_file['type'], $allowed_mime_types) && 
		!in_array($file_info['type'], $allowed_mime_types) && 
		!in_array($file_ext, $allowed_extensions)) {
		
		error_log('Invalid file type: ' . $logo_file['type'] . ' with extension: ' . $file_ext);
		wp_send_json_error('Invalid file type. Allowed formats: JPEG, PNG, GIF, SVG, PDF, AI, EPS');
		exit;
	}
	
	// Generate unique filename
	$filename = wp_unique_filename($logo_dir, $logo_file['name']);
	$logo_path = $logo_dir . '/' . $filename;
	
	error_log('Saving to: ' . $logo_path);
	
	// Move uploaded file
	if (move_uploaded_file($logo_file['tmp_name'], $logo_path)) {
		$logo_url = $upload_dir['baseurl'] . '/cllf-uploads/' . $filename;
		error_log('Logo saved successfully at: ' . $logo_url);
		
		// Return success with extra information
		wp_send_json_success(array(
			'url' => $logo_url,
			'type' => $logo_file['type'],
			'extension' => $file_ext
		));
	} else {
		error_log('Failed to move uploaded file from ' . $logo_file['tmp_name'] . ' to ' . $logo_path);
		wp_send_json_error('Failed to save logo file');
	}
}

/**
 * AJAX handler for color extraction
 */
function cllf_extract_logo_colors_ajax() {
	// Log incoming request for debugging
	error_log('Color extraction AJAX request received');
	
	// Verify nonce
	if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'cllf-nonce')) {
		error_log('Color extraction nonce verification failed');
		wp_send_json_error('Security check failed');
		exit;
	}
	
	// Get the logo URL
	$logo_url = isset($_POST['logo_url']) ? sanitize_text_field($_POST['logo_url']) : '';
	
	if (empty($logo_url)) {
		error_log('No logo URL provided for color extraction');
		wp_send_json_error('No logo URL provided');
		exit;
	}
	
	error_log('Attempting to extract colors from: ' . $logo_url);
	
	// Convert URL to path
	$upload_dir = wp_upload_dir();
	$logo_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $logo_url);
	
	error_log('Converted to file path: ' . $logo_path);
	
	// Check if file exists
	if (!file_exists($logo_path)) {
		error_log('Logo file does not exist at path: ' . $logo_path);
		wp_send_json_error('Logo file not found');
		exit;
	}
	
	// Extract colors
	$colors = cllf_extract_colors($logo_path);
	error_log('Extracted colors: ' . print_r($colors, true));
	
	// Generate color names
	$color_data = array();
	foreach ($colors as $index => $hex) {
		$color_name = ($index === 0 && $hex === '#000000') ? 'Black' : 'Logo Color ' . $index;
		$color_data[] = array(
			'hex' => $hex,
			'name' => $color_name
		);
	}
	
	error_log('Sending back color data: ' . print_r($color_data, true));
	wp_send_json_success($color_data);
}

// Register AJAX actions
add_action('wp_ajax_cllf_temp_logo_upload', 'cllf_temp_logo_upload_ajax');
add_action('wp_ajax_nopriv_cllf_temp_logo_upload', 'cllf_temp_logo_upload_ajax');

add_action('wp_ajax_cllf_extract_logo_colors', 'cllf_extract_logo_colors_ajax');
add_action('wp_ajax_nopriv_cllf_extract_logo_colors', 'cllf_extract_logo_colors_ajax');

// Simple AJAX test endpoint for troubleshooting
add_action('wp_ajax_cllf_test_ajax', 'cllf_test_ajax');
add_action('wp_ajax_nopriv_cllf_test_ajax', 'cllf_test_ajax');

function cllf_test_ajax() {
	wp_send_json_success('AJAX is working');
}