<?php
// First, ensure this can't be accessed directly outside WordPress
if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

// Include helper functions
require_once dirname(__FILE__) . '/includes/helper-functions.php';

/**
 * Generate readme.html file from GitHub commit history
 */

// Define debug function that logs to a file instead of outputting to browser
function debug_log($message) {
	// Only log to file when debugging is enabled
	if (defined('CLLF_DEBUG') && CLLF_DEBUG) {
		$log_file = dirname(__FILE__) . '/readme-generator.log';
		file_put_contents($log_file, $message . "\n", FILE_APPEND);
	}
}

// Check if this script is being run directly or included
$is_direct_execution = (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__));

// GitHub repository information - only used when run directly
$default_owner = 'rynoceris';
$default_repo = 'Texon-Custom-Loop-Form';
$default_token = cllf_get_github_token(); // Create a personal access token on GitHub

// Get commits from GitHub API with pagination
function get_commits($owner, $repo, $token, $page = 1, $per_page = 100) {
	$all_commits = [];
	$has_more = true;
	
	debug_log("Fetching commits, page $page...");
	
	while ($has_more) {
		$url = "https://api.github.com/repos/{$owner}/{$repo}/commits?per_page={$per_page}&page={$page}";
		debug_log("Fetching from URL: $url");
		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'User-Agent: PHP Script',
			'Accept: application/vnd.github.v3+json',
			'Authorization: token ' . $token
		]);
		
		$response = curl_exec($ch);
		
		// Check for curl errors
		if ($response === false) {
			debug_log("cURL Error: " . curl_error($ch));
			break;
		}
		
		$commits = json_decode($response, true);
		
		// Check if there was an error
		if (isset($commits['message'])) {
			debug_log("GitHub API Error: " . $commits['message']);
			if (isset($commits['documentation_url'])) {
				debug_log("See: " . $commits['documentation_url']);
			}
			break;
		}
		
		// No more commits
		if (empty($commits)) {
			debug_log("No more commits on page $page");
			$has_more = false;
			break;
		}
		
		debug_log("Found " . count($commits) . " commits on page $page");
		
		// For each commit, fetch its full details to get complete commit message
		foreach ($commits as $commit) {
			$commit_sha = $commit['sha'];
			debug_log("Fetching details for commit: $commit_sha");
			
			$commit_url = "https://api.github.com/repos/{$owner}/{$repo}/commits/{$commit_sha}";
			
			curl_setopt($ch, CURLOPT_URL, $commit_url);
			$commit_response = curl_exec($ch);
			
			if ($commit_response === false) {
				debug_log("cURL Error fetching commit: " . curl_error($ch));
				continue;
			}
			
			$commit_detail = json_decode($commit_response, true);
			
			if (isset($commit_detail['message'])) {
				debug_log("GitHub API Error fetching commit: " . $commit_detail['message']);
				continue;
			}
			
			$all_commits[] = $commit_detail;
			debug_log("Added commit: " . substr($commit_detail['commit']['message'], 0, 40) . "...");
		}
		
		$page++;
		debug_log("Moving to page $page");
	}
	
	curl_close($ch);
	debug_log("Total commits fetched: " . count($all_commits));
	return $all_commits;
}

// Create version entries directly from commits - one commit = one entry
function create_version_entries($commits) {
	// Sort commits by date (newest first)
	usort($commits, function($a, $b) {
		$date_a = strtotime($a['commit']['author']['date']);
		$date_b = strtotime($b['commit']['author']['date']);
		return $date_b - $date_a; // Sort newest first
	});
	
	$versions = [];
	
	debug_log("Processing " . count($commits) . " commits into version entries");
	
	// Define known version commits with specific hashes
	$known_versions = [
		'388a3f41d2d58c7175e46a46455b05128440673b' => '2.1',   // v2.1 updates
		'2451b96bfb2e79a2e188d020c72283d6a1260e69' => '2.0',   // Major update to version 2.0
		'fe78db7b13ebc68ef350cc698b41925c5ad138d8' => '1.0',   // Initial Commit
		'8acd14fc0743570584929cad18c4e846c5c4d53a' => '2.0.2', // Most recent version
		'10f1e6a9ecc23abdc405270849831ce63a3e6d4c' => '2.0.1', // Added form title
	];
	
	// Get the latest version for future versioning
	$latest_version = '2.3.2'; // Default start
	
	// Determine highest known version
	foreach ($known_versions as $sha => $version) {
		if (version_compare($version, $latest_version, '>')) {
			$latest_version = $version;
		}
	}
	
	debug_log("Highest known version: $latest_version");
	
	// Track commits that have been processed
	$processed_shas = array_keys($known_versions);
	
	foreach ($commits as $index => $commit) {
		$commit_message = $commit['commit']['message'];
		$message_parts = explode("\n", $commit_message, 2);
		$title = trim($message_parts[0]);
		$description = isset($message_parts[1]) ? trim($message_parts[1]) : '';
		$date = date('F j, Y', strtotime($commit['commit']['author']['date']));
		$sha = $commit['sha'];
		
		// Set version from known versions or generate a new one for new commits
		if (isset($known_versions[$sha])) {
			$version = $known_versions[$sha];
			debug_log("Using predefined version $version for commit: $sha");
		} else {
			// Auto-version new commits that aren't in our known list
			// Try to extract version from the commit message first
			$extracted_version = null;
			if (preg_match('/version\s+(\d+\.\d+(\.\d+)?)/', strtolower($title), $matches)) {
				$extracted_version = $matches[1];
				debug_log("Extracted version $extracted_version from commit message");
			} elseif (preg_match('/v(\d+\.\d+(\.\d+)?)/', $title, $matches)) {
				$extracted_version = $matches[1];
				debug_log("Extracted version $extracted_version from commit message");
			}
			
			if ($extracted_version) {
				$version = $extracted_version;
			} else {
				// Generate next micro version based on latest version
				$version_parts = explode('.', $latest_version);
				
				// If we have 2 parts (major.minor), add micro version
				if (count($version_parts) == 2) {
					$version = $latest_version . '.1';
				} else {
					// Increment the micro version
					$version_parts[2] = intval($version_parts[2]) + 1;
					$version = implode('.', $version_parts);
				}
				
				debug_log("Generated new version $version for commit: $sha");
				$latest_version = $version; // Update for next commit
			}
		}
		
		// Process changes based on commit message
		$changes = [];
		
		// Always include the title as the primary change
		$changes[] = $title;
		
		// Process description if available
		if (!empty($description)) {
			// Split by lines and process each
			$lines = explode("\n", $description);
			$current_section = [];
			
			foreach ($lines as $line) {
				$line = trim($line);
				if (empty($line)) {
					// Empty line - if we have a current section, add it and reset
					if (!empty($current_section)) {
						$changes[] = implode(" ", $current_section);
						$current_section = [];
					}
					continue;
				}
				
				// Look for list markers
				if (preg_match('/^[\-\*\+]\s+(.+)$/', $line, $matches)) {
					// This is a list item, add it as a separate change
					if (!empty($current_section)) {
						$changes[] = implode(" ", $current_section);
						$current_section = [];
					}
					$changes[] = $matches[1];
				} else {
					// Not a list item, add to current section
					$current_section[] = $line;
				}
			}
			
			// Add any remaining section
			if (!empty($current_section)) {
				$changes[] = implode(" ", $current_section);
			}
		}
		
		// Make sure we have at least one change
		if (empty($changes)) {
			$changes[] = $title ?: "Code updates";
		}
		
		// Create version entry
		$versions[] = [
			'version' => $version,
			'date' => $date,
			'title' => $title,
			'changes' => $changes,
			'sha' => $sha
		];
		
		debug_log("Created version entry: $version with " . count($changes) . " changes");
		
		// Track this sha as processed
		$processed_shas[] = $sha;
	}
	
	// Keep version entries unique by version number, preferring the one with more changes
	$unique_versions = [];
	foreach ($versions as $entry) {
		$version = $entry['version'];
		if (!isset($unique_versions[$version]) || 
			count($entry['changes']) > count($unique_versions[$version]['changes'])) {
			$unique_versions[$version] = $entry;
		}
	}
	
	// Convert back to indexed array
	$versions = array_values($unique_versions);
	
	// Sort by version number, newest first
	usort($versions, function($a, $b) {
		return version_compare($b['version'], $a['version']);
	});
	
	debug_log("Processed " . count($versions) . " unique version entries");
	return $versions;
}

// Generate HTML with environment versions
function generate_html($versions, $env_versions = null) {
	debug_log("Generating HTML...");
	
	// If environment versions weren't passed, try to detect them
	if ($env_versions === null) {
		// Get current environment versions
		$env_versions = [
			'wordpress' => defined('ABSPATH') ? get_bloginfo('version') : 'Unknown',
			'woocommerce' => defined('WC_VERSION') ? WC_VERSION : 'Unknown',
			'php' => phpversion()
		];
	}
	
	// Get latest plugin version (first in our sorted list)
	$latest_plugin_version = isset($versions[0]) ? $versions[0]['version'] : '2.1';
	
	// Get current year for copyright
	$current_year = date('Y');
	
	debug_log("Using versions - WordPress: {$env_versions['wordpress']}, WooCommerce: {$env_versions['woocommerce']}, PHP: {$env_versions['php']}");
	
	$html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Custom Laundry Loops Form - WordPress Plugin</title>
	<style>
		body {
			font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
			color: #444;
			max-width: 800px;
			margin: 0 auto;
			padding: 20px;
		}
		h1 {
			color: #23282d;
			border-bottom: 1px solid #eee;
			padding-bottom: 10px;
		}
		h2 {
			color: #23282d;
			font-size: 1.4em;
			margin-top: 30px;
		}
		h3 {
			color: #23282d;
			font-size: 1.2em;
		}
		.plugin-info {
			background-color: #f9f9f9;
			padding: 15px;
			border-radius: 5px;
			margin-bottom: 20px;
		}
		.plugin-info p {
			margin: 5px 0;
		}
		.changelog-item {
			margin-bottom: 30px;
		}
		.changelog-item h3 {
			background-color: #f0f0f0;
			padding: 8px 12px;
			border-radius: 4px;
			margin-bottom: 10px;
		}
		ul {
			margin-left: 20px;
		}
		.footer {
			margin-top: 50px;
			padding-top: 20px;
			border-top: 1px solid #eee;
			font-size: 0.9em;
			color: #777;
		}
	</style>
</head>
<body>
	<h1>Custom Laundry Loops Form</h1>
	
	<div class="plugin-info">
		<p><strong>Contributors:</strong> Texon Towel, Ryan Ours</p>
		<p><strong>Tags:</strong> custom, laundry loops, woocommerce</p>
		<p><strong>Requires WordPress:</strong> 6.5+</p>
		<p><strong>Tested up to:</strong> {$env_versions['wordpress']}</p>
		<p><strong>Requires WooCommerce:</strong> 8.8.x+</p>
		<p><strong>Tested up to:</strong> {$env_versions['woocommerce']}</p>
		<p><strong>Requires PHP:</strong> 8.1</p>
		<p><strong>Tested up to:</strong> {$env_versions['php']}</p>
		<p><strong>Stable tag:</strong> {$latest_plugin_version}</p>
		<p><strong>License:</strong> GPLv3 or later</p>
		<p><strong>License URI:</strong> <a href="https://www.gnu.org/licenses/gpl-3.0.en.html">https://www.gnu.org/licenses/gpl-3.0.en.html</a></p>
	</div>
	
	<h2>Description</h2>
	<p>The Custom Laundry Loops Form plugin allows customers to order custom laundry loops with various options directly from your WooCommerce store. It provides a user-friendly form with color selection, clip types, and personalization options.</p>
	
	<h2>Features</h2>
	<ul>
		<li>Color selection with visual preview</li>
		<li>Single and double clip options</li>
		<li>Logo upload capability (JPG, PNG, SVG, PDF, AI formats)</li>
		<li>Custom text for loops</li>
		<li>Numbers or names on loops</li>
		<li>Multiple quantity options</li>
		<li>Custom font selection</li>
		<li>Text color customization</li>
		<li>Live preview of loops</li>
		<li>Direct integration with WooCommerce cart and checkout</li>
	</ul>
	
	<h2>Installation</h2>
	<ol>
		<li>Upload the plugin files to the <code>/wp-content/plugins/custom-laundry-loops-form</code> directory</li>
		<li>Activate the plugin through the 'Plugins' menu in WordPress</li>
		<li>Place the shortcode <code>[custom_laundry_loops_form]</code> on any page where you want the form to appear</li>
	</ol>
	
	<h2>Frequently Asked Questions</h2>
	
	<h3>How do I display the form?</h3>
	<p>Use the shortcode <code>[custom_laundry_loops_form]</code> on any page.</p>
	
	<h3>Can customers upload their own logos?</h3>
	<p>Yes, the form includes an option for logo upload in various formats including PNG, JPG, SVG, AI, and PDF.</p>
	
	<h2>Changelog</h2>
HTML;

	// Add each version entry to the HTML
	foreach ($versions as $version) {
		debug_log("Adding version {$version['version']} to HTML with " . count($version['changes']) . " changes");
		
		$html .= "\n    <div class=\"changelog-item\">\n";
		$html .= "        <h3>Version {$version['version']} - {$version['date']}</h3>\n";
		
		if (!empty($version['changes'])) {
			$html .= "        <ul>\n";
			foreach ($version['changes'] as $change) {
				$html .= "            <li>" . htmlspecialchars($change) . "</li>\n";
			}
			$html .= "        </ul>\n";
		} else {
			$html .= "        <p>No specific changes recorded for this version.</p>\n";
		}
		
		$html .= "    </div>\n";
	}
	
	$html .= <<<HTML
	
	<div class="footer">
		<p>© {$current_year} Texon Towel - Custom Laundry Loops Form Plugin</p>
	</div>
</body>
</html>
HTML;
	
	return $html;
}

// Run main execution only if this script is being called directly
if ($is_direct_execution) {
	// Define debug mode for direct execution
	define('CLLF_DEBUG', true);
	
	debug_log("Starting script...");
	
	try {
		debug_log("Starting to fetch commits...");
		$commits = get_commits($default_owner, $default_repo, $default_token);
		debug_log("Found " . count($commits) . " commits");

		debug_log("Creating version entries...");
		$versions = create_version_entries($commits);
		debug_log("Created " . count($versions) . " version entries");

		debug_log("Generating HTML...");
		$html = generate_html($versions);

		// Save the HTML to a file
		file_put_contents(dirname(__FILE__) . '/readme.html', $html);

		debug_log("readme.html file generated successfully!");
		
		// Provide feedback when run directly
		echo "readme.html generated successfully. Check readme-generator.log for details.\n";
	} catch (Exception $e) {
		debug_log("Error: " . $e->getMessage());
		echo "Error generating readme.html: " . $e->getMessage() . "\n";
	}
}