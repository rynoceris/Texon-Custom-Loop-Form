<?php
/**
 * Helper functions for the Custom Laundry Loops Form plugin
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Encrypt the GitHub token before storing
 * 
 * @param string $token The token to encrypt
 * @return string The encrypted token
 */
function cllf_encrypt_token($token) {
	if (empty($token)) return '';
	$key = wp_salt('auth');
	return openssl_encrypt($token, 'AES-256-CBC', $key, 0, substr(md5($key), 0, 16));
}

/**
 * Decrypt the stored GitHub token
 * 
 * @param string $encrypted_token The encrypted token
 * @return string The decrypted token
 */
function cllf_decrypt_token($encrypted_token) {
	if (empty($encrypted_token)) return '';
	$key = wp_salt('auth');
	return openssl_decrypt($encrypted_token, 'AES-256-CBC', $key, 0, substr(md5($key), 0, 16));
}

/**
 * Save the GitHub token securely
 * 
 * @param string $token The token to save
 */
function cllf_save_github_token($token) {
	$encrypted = cllf_encrypt_token($token);
	update_option('cllf_github_token', $encrypted);
	return true;
}

/**
 * Get the GitHub token
 * 
 * @return string The GitHub token or empty string if not set
 */
function cllf_get_github_token() {
	$encrypted = get_option('cllf_github_token', '');
	return cllf_decrypt_token($encrypted);
}

/**
 * Display admin notice if GitHub token is missing
 */
function cllf_check_github_token() {
	// Only show to administrators who can set the token
	if (!current_user_can('manage_options')) {
		return;
	}
	
	// Check if we're already on the settings page
	$screen = get_current_screen();
	if (isset($screen->id) && $screen->id === 'settings_page_cllf-github-settings') {
		return;
	}
	
	// Check if token is missing
	if (empty(cllf_get_github_token())) {
		?>
		<div class="notice notice-warning is-dismissible">
			<p>
				<?php _e('Custom Laundry Loops Form plugin requires a GitHub personal access token to function properly.', 'custom-laundry-loops-form'); ?>
				<a href="<?php echo esc_url(admin_url('options-general.php?page=cllf-github-settings')); ?>">
					<?php _e('Set up now', 'custom-laundry-loops-form'); ?>
				</a>
			</p>
		</div>
		<?php
	}
}

/**
 * Generate a static readme.html with a notice about the missing token
 * 
 * @return string The HTML content for the readme file
 */
function cllf_generate_token_notice_html() {
	return '<!DOCTYPE html>
	<html>
	<head>
		<title>Custom Laundry Loops Form - Setup Required</title>
		<style>
			body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; padding: 20px; line-height: 1.5; }
			.notice { background-color: #fff8e5; border-left: 4px solid #ffb900; padding: 12px; margin-bottom: 20px; }
			.button { display: inline-block; background: #2271b1; border: 1px solid #2271b1; border-radius: 3px; color: white; cursor: pointer; font-size: 13px; font-weight: 600; padding: 0 10px; text-decoration: none; line-height: 2.15384615; margin: 10px 0; }
			.button:hover { background: #135e96; border-color: #135e96; }
		</style>
	</head>
	<body>
		<h1>Custom Laundry Loops Form</h1>
		<div class="notice">
			<p><strong>Setup Required:</strong> GitHub Personal Access Token is missing.</p>
			<p>This plugin requires a GitHub token to generate the changelog from repository commits.</p>
			<p>Please visit the <a href="' . admin_url('options-general.php?page=cllf-github-settings') . '" target="_parent">GitHub Settings page</a> to configure your token.</p>
		</div>
		<p><strong>Why is this needed?</strong><br>
		The GitHub token allows the plugin to access repository information to build this documentation automatically from commit history.</p>
	</body>
	</html>';
}