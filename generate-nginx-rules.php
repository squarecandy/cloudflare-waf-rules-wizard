<?php
/**
 * generate-nginx-rules.php
 *
 * Generates two nginx configs from the lists maintained in rules.php.
 * Run from the project root: php generate-nginx-rules.php
 *
 * Output:
 *   nginx/bot-blocking.conf          — no wp-login block (safe for any site)
 *   nginx/bot-blocking-wp-login.conf — includes wp-login block
 *                                      (requires WPS Hide Login or equivalent)
 */

// ──────────────────────────────────────────────────────────────────────────────
// Pull shared data from rules.php
// rules.php uses define() guards and Cloudflare-specific constants — we shim
// the one constant it checks so the file can be required safely.
// ──────────────────────────────────────────────────────────────────────────────
if ( ! defined( 'CLOUDFLARE_API_KEY' ) ) {
	define( 'CLOUDFLARE_API_KEY', 'generate-nginx-shim' );
}
if ( ! defined( 'CLOUDFLARE_EMAIL' ) ) {
	define( 'CLOUDFLARE_EMAIL', 'generate-nginx-shim' );
}
if ( ! defined( 'CLOUDFLARE_ACCOUNT_IDS' ) ) {
	define( 'CLOUDFLARE_ACCOUNT_IDS', array() );
}

// Capture only the variable declarations from rules.php — stop before the
// ruleset arrays that reference undefined functions / globals.
require_once __DIR__ . '/rules.php';

// ──────────────────────────────────────────────────────────────────────────────
// 1. PATH PATTERNS  →  nginx location regex block
//    Source: $wp_path_strings  +  hardcoded Drupal prefix paths
// ──────────────────────────────────────────────────────────────────────────────

// Drupal prefix paths get their own ^~ location blocks (prevents the regex
// location below from firing on the same request, giving a slight perf win).
$drupal_prefix_paths = array(
	'/sites/default/files/',
	'/sites/all/',
	'/node',
);

// Convert $wp_path_strings entries to nginx regex fragments.
// nginx ~* location regex is matched against $uri (decoded path, no query string).
// Mappings that need special handling:
$nginx_path_map = array(
	'/.env'       => '/\.env',      // escape the dot
	'network.php' => 'network\.php',
	'wp-ajf.php'  => 'wp-ajf\.php',
	'/tel%3a'     => '/tel(%3a|:)', // match both encoded and literal in one pattern
	'/tel:'       => null,          // covered by the combined pattern above — skip
	'/mailto'     => '/mailto(:|%3a)', // covers /mailto: and /mailto%3a
);

$path_regex_parts = array();
foreach ( $wp_path_strings as $entry ) {
	$entry = trim( $entry );

	// Skip entries that are superseded by a combined pattern above
	if ( array_key_exists( $entry, $nginx_path_map ) && null === $nginx_path_map[ $entry ] ) {
		continue;
	}

	// Already has a custom nginx pattern
	if ( array_key_exists( $entry, $nginx_path_map ) ) {
		$path_regex_parts[] = $nginx_path_map[ $entry ];
		continue;
	}

	// Generic: escape dots, leave everything else as-is
	$path_regex_parts[] = str_replace( '.', '\.', $entry );
}

// Add civicrm (lives in $drupal in CF but belongs in the path regex here)
$path_regex_parts[] = 'civicrm';
// /javascript$ — end-of-string anchor works in nginx regex too
$path_regex_parts[] = '/javascript$';

$path_regex = implode( '|', $path_regex_parts );

// ──────────────────────────────────────────────────────────────────────────────
// 2. USER-AGENT PATTERNS  →  nginx if block
//    Source: $aggressive_crawlers array (before the array_map transform)
// ──────────────────────────────────────────────────────────────────────────────

// Rebuild from the raw array defined in rules.php (before array_map transforms it).
// We need to re-require the raw list, so we re-read the file and extract it.
$rules_source = file_get_contents( __DIR__ . '/rules.php' );

// Extract the $aggressive_crawlers array literal from source
preg_match(
	'/\$aggressive_crawlers\s*=\s*array\s*\((.*?)\);/s',
	$rules_source,
	$matches
);

$ua_entries = array();
if ( ! empty( $matches[1] ) ) {
	// Pull out all single-quoted string values, ignore commented-out lines
	preg_match_all( "/^\s*'([^']+)'/m", $matches[1], $string_matches );
	foreach ( $string_matches[1] as $entry ) {
		$entry = trim( $entry );
		// Skip entries that are on commented-out lines
		$ua_entries[] = $entry;
	}
}

// Additional UA strings present in the nginx sample that aren't in the CF list
// (either because CF's verified_bot_category catches them, or they were omitted).
// Add here to keep the nginx ruleset independently comprehensive.
$nginx_extra_uas = array(
	'chatgpt-user',   // OpenAI ChatGPT browsing
	'claude-web',     // Anthropic browser agent
	'google-extended', // Google Bard/Gemini training
	'imagesiftbot',   // image scraper
	'omgili',         // web intelligence crawler
	'orbbot',         // aggressive proxy bot
	'freshbot',       // content scraper
	'goodzer',
	'jorgee',         // vulnerability scanner
	'mozlila',        // fake Mozilla UA used by scrapers
	'python-requests', // Python requests library
	'curl/',          // raw curl — almost never a real user on a WP site
	'wp-cli',         // should never come from outside the server
);

$all_ua_entries = array_unique( array_merge( $ua_entries, $nginx_extra_uas ) );
sort( $all_ua_entries );

// Escape special nginx regex chars in UA strings (. is the main one)
$ua_regex_parts = array_map(
	function ( $entry ) {
		return str_replace( '.', '\.', $entry );
	},
	$all_ua_entries
);

$ua_regex = implode( '|', $ua_regex_parts );

// ──────────────────────────────────────────────────────────────────────────────
// 3. Render function
// ──────────────────────────────────────────────────────────────────────────────

/**
 * Render the main nginx bot-blocking config (everything except wp-login).
 *
 * @param string $filename       Basename used in the header comment.
 * @param string $timestamp      Generation timestamp string.
 * @param array  $drupal_prefixes Prefix paths for ^~ location blocks.
 * @param string $path_regex     Compiled path regex string.
 * @param string $ua_regex       Compiled UA regex string.
 * @return string
 */
function render_nginx_conf( $filename, $timestamp, $drupal_prefixes, $path_regex, $ua_regex ) {
	$output = <<<NGINX
# ============================================================================
# {$filename} — AUTO-GENERATED, DO NOT EDIT BY HAND
# Generated: {$timestamp}
# Source:    rules.php  (edit that file, then re-run generate-nginx-rules.php)
# ============================================================================
# Include this file inside your nginx server {} block, e.g.:
#   include /path/to/{$filename};
# Also include bot-blocking-wp-login.conf if wp-login.php protection is needed.
# ============================================================================

# ─────────────────────────────────────────────────
# Drupal / Node probe paths — prefix match
# (^~ keeps the regex location below from firing)
# ─────────────────────────────────────────────────
NGINX;

	foreach ( $drupal_prefixes as $path ) {
		$padded  = str_pad( $path, 30 );
		$output .= "\nlocation ^~ {$padded} { return 444; }";
	}

	$output .= <<<NGINX


# ─────────────────────────────────────────────────
# Path-based probe patterns — single regex block
# ─────────────────────────────────────────────────
location ~* ({$path_regex}) {
	return 444;
}

# ─────────────────────────────────────────────────
# Bad bot user-agents — case-insensitive
# ─────────────────────────────────────────────────
if (\$http_user_agent ~* "{$ua_regex}") {
	return 444;
}
NGINX;

	return $output;
}

/**
 * Render the wp-login-only nginx config.
 *
 * @param string $filename  Basename used in the header comment.
 * @param string $timestamp Generation timestamp string.
 * @return string
 */
function render_wp_login_conf( $filename, $timestamp ) {
	return <<<NGINX
# ============================================================================
# {$filename} — AUTO-GENERATED, DO NOT EDIT BY HAND
# Generated: {$timestamp}
# Source:    rules.php  (edit that file, then re-run generate-nginx-rules.php)
# ============================================================================
# Include alongside bot-blocking.conf when wp-login.php protection is needed
# and /wp-login.php is the real login URL (i.e. WPS Hide Login is NOT active).
#   include /path/to/bot-blocking.conf;
#   include /path/to/{$filename};
# ============================================================================

# ─────────────────────────────────────────────────
# wp-login.php protection
# Allows ?action=logout and ?action=postpass through.
# ─────────────────────────────────────────────────
set \$block_wp_login 0;
if (\$uri = /wp-login.php) {
	set \$block_wp_login 1;
}
if (\$arg_action = logout) {
	set \$block_wp_login 0;
}
if (\$arg_action = postpass) {
	set \$block_wp_login 0;
}
if (\$block_wp_login = 1) {
	return 444;
}
NGINX;
}

// ──────────────────────────────────────────────────────────────────────────────
// 4. Write output files
// ──────────────────────────────────────────────────────────────────────────────

$timestamp  = date( 'Y-m-d H:i:s' );
$output_dir = __DIR__ . '/nginx';

if ( ! is_dir( $output_dir ) ) {
	mkdir( $output_dir, 0755, true );
}

// Main rules file — all path/UA blocking, no wp-login section
$content = render_nginx_conf(
	'bot-blocking.conf',
	$timestamp,
	$drupal_prefix_paths,
	$path_regex,
	$ua_regex
);
file_put_contents( $output_dir . '/bot-blocking.conf', $content );
echo 'Generated: nginx/bot-blocking.conf          (path/UA rules, no wp-login)' . PHP_EOL;

// wp-login-only file — include alongside bot-blocking.conf when needed
$wp_login_content = render_wp_login_conf( 'bot-blocking-wp-login.conf', $timestamp );
file_put_contents( $output_dir . '/bot-blocking-wp-login.conf', $wp_login_content );
echo 'Generated: nginx/bot-blocking-wp-login.conf (wp-login rule only)' . PHP_EOL;

echo '  Path patterns : ' . count( $path_regex_parts ) . PHP_EOL;
echo '  UA patterns   : ' . count( $all_ua_entries ) . PHP_EOL;
