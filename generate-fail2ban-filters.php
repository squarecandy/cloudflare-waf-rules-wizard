<?php
/**
 * generate-fail2ban-filters.php
 *
 * Generates fail2ban filter .local files from the bot lists maintained in rules.php.
 * Run from the project root: php generate-fail2ban-filters.php
 *
 * Output:
 *   fail2ban-filters/sqcdy-badbots.local   — badbotscustom UA pattern list
 *   fail2ban-filters/sqcdy-wp-custom.local — wpcustompaths WP probe path list
 *
 * Deploy to server:
 *   scp fail2ban-filters/sqcdy-*.local fail2ban-filters/sqcdy-badbots.conf fail2ban-filters/sqcdy-wp-custom.conf user@host:/etc/fail2ban/filter.d/
 *   ssh user@host "sudo fail2ban-client reload"
 *
 * Requirements:
 *   sqcdy-badbots.conf failregex references %(badbotscustom)s.
 *   sqcdy-wp-custom.conf failregex references %(wpcustompaths)s.
 *   Case-insensitive matching is handled by the (?i) flag embedded in each failregex.
 */

// ──────────────────────────────────────────────────────────────────────────────
// Shim constants so rules.php can be loaded safely outside the web context.
// ──────────────────────────────────────────────────────────────────────────────
if ( ! defined( 'CLOUDFLARE_API_KEY' ) ) {
	define( 'CLOUDFLARE_API_KEY', 'generate-fail2ban-shim' );
}
if ( ! defined( 'CLOUDFLARE_EMAIL' ) ) {
	define( 'CLOUDFLARE_EMAIL', 'generate-fail2ban-shim' );
}
if ( ! defined( 'CLOUDFLARE_ACCOUNT_IDS' ) ) {
	define( 'CLOUDFLARE_ACCOUNT_IDS', array() );
}
if ( ! defined( 'FAIL2BAN_LIST_ID' ) ) {
	define( 'FAIL2BAN_LIST_ID', 'cf_fail2ban_blocked' );
}

require_once __DIR__ . '/rules.php';

// ──────────────────────────────────────────────────────────────────────────────
// Build badbotscustom — pipe-separated UA substrings from $aggressive_crawlers_all.
// Patterns are lowercase; case-insensitive matching is handled by (?i) in the failregex.
// ──────────────────────────────────────────────────────────────────────────────
$ua_entries = array_unique( $aggressive_crawlers_all );
sort( $ua_entries );

$badbotscustom = implode( '|', $ua_entries );

// ──────────────────────────────────────────────────────────────────────────────
// Write output file
// ──────────────────────────────────────────────────────────────────────────────
$timestamp  = date( 'Y-m-d H:i:s' );
$output_dir = __DIR__ . '/fail2ban-filters';

if ( ! is_dir( $output_dir ) ) {
	mkdir( $output_dir, 0755, true );
}

$content  = '# ============================================================================' . PHP_EOL;
$content .= '# sqcdy-badbots.local — AUTO-GENERATED, DO NOT EDIT BY HAND' . PHP_EOL;
$content .= '# Generated: ' . $timestamp . PHP_EOL;
$content .= '# Source:    rules.php  (edit that file, then re-run generate-fail2ban-filters.php)' . PHP_EOL;
$content .= '#' . PHP_EOL;
$content .= '# Overrides badbotscustom in sqcdy-badbots.conf (failregex lives in the .conf).' . PHP_EOL;
$content .= '# Deploy: scp fail2ban-filters/sqcdy-badbots.local user@host:/etc/fail2ban/filter.d/' . PHP_EOL;
$content .= '#         ssh user@host "sudo fail2ban-client reload"' . PHP_EOL;
$content .= '# ============================================================================' . PHP_EOL;
$content .= PHP_EOL;
$content .= '[Definition]' . PHP_EOL;
$content .= 'badbotscustom = ' . $badbotscustom . PHP_EOL;

$outfile = $output_dir . '/sqcdy-badbots.local';
file_put_contents( $outfile, $content );

echo 'Generated: fail2ban-filters/sqcdy-badbots.local' . PHP_EOL;
echo '  UA patterns: ' . count( $ua_entries ) . PHP_EOL;
echo PHP_EOL;

// ──────────────────────────────────────────────────────────────────────────────
// Build wpcustompaths — pipe-separated path regexes from $wp_path_strings.
// Href injection probes are excluded: they contain % (breaks Python formatting)
// and are not useful patterns in nginx access log URI paths.
// ──────────────────────────────────────────────────────────────────────────────
$exclude_paths         = array( '/tel:', '/tel%3a', '/mailto' );
$wp_paths_for_fail2ban = array_values(
	array_filter(
		$wp_path_strings,
		function ( $path ) use ( $exclude_paths ) {
			return ! in_array( $path, $exclude_paths, true );
		}
	)
);
$wpcustompaths         = implode( '|', array_map( 'preg_quote', $wp_paths_for_fail2ban ) );

$wp_content  = '# ============================================================================' . PHP_EOL;
$wp_content .= '# sqcdy-wp-custom.local — AUTO-GENERATED, DO NOT EDIT BY HAND' . PHP_EOL;
$wp_content .= '# Generated: ' . $timestamp . PHP_EOL;
$wp_content .= '# Source:    $wp_path_strings in rules.php  (edit that file, then re-run generate-fail2ban-filters.php)' . PHP_EOL;
$wp_content .= '#' . PHP_EOL;
$wp_content .= '# Overrides wpcustompaths in sqcdy-wp-custom.conf (failregex lives in the .conf).' . PHP_EOL;
$wp_content .= '# Deploy: scp fail2ban-filters/sqcdy-wp-custom.local user@host:/etc/fail2ban/filter.d/' . PHP_EOL;
$wp_content .= '#         ssh user@host "sudo fail2ban-client reload"' . PHP_EOL;
$wp_content .= '# ============================================================================' . PHP_EOL;
$wp_content .= PHP_EOL;
$wp_content .= '[Definition]' . PHP_EOL;
$wp_content .= 'wpcustompaths = ' . $wpcustompaths . PHP_EOL;

$wp_outfile = $output_dir . '/sqcdy-wp-custom.local';
file_put_contents( $wp_outfile, $wp_content );

echo 'Generated: fail2ban-filters/sqcdy-wp-custom.local' . PHP_EOL;
echo '  Path patterns: ' . count( $wp_paths_for_fail2ban ) . PHP_EOL;
echo PHP_EOL;
echo 'Static files (deploy alongside generated files):' . PHP_EOL;
$static_files = array(
	'sqcdy-badbots.conf',        // failregex lives here; badbotscustom set in .local
	'sqcdy-404-php.conf',        // single-file filter (4xx only)
	'sqcdy-fast-404.conf',       // single-file filter
	'sqcdy-php-redirect.conf',   // single-file filter (301/302 probes)
	'sqcdy-wp-custom.conf',      // failregex lives here; wpcustompaths set in .local
	'sqcdy-wp-login.conf',       // single-file filter
);
foreach ( $static_files as $file ) {
	echo '  fail2ban-filters/' . $file . PHP_EOL;
}
