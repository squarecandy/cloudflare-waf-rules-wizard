<?php
/**
 * Generate and write a fail2ban sync config for a configured server.
 *
 * Usage:
 *   php fail2ban-scripts/generate-fail2ban-config.php <server-slug>
 *
 * Output:
 *   Writes fail2ban-scripts/generated/cloudflare-fail2ban-config-<server-slug>.txt
 *   Prints the relative output path on success.
 */

if ( PHP_SAPI !== 'cli' ) {
	fwrite( STDERR, "This script must be run from CLI.\n" );
	exit( 1 );
}

$project_root = dirname( __DIR__ );
require_once $project_root . '/config.php';
require_once $project_root . '/functions.php';

$server_slug = isset( $argv[1] ) ? trim( $argv[1] ) : '';
if ( '' === $server_slug ) {
	fwrite( STDERR, "Usage: php fail2ban-scripts/generate-fail2ban-config.php <server-slug>\n" );
	exit( 1 );
}

$fail2ban_servers = defined( 'FAIL2BAN_SERVERS' ) ? FAIL2BAN_SERVERS : array();
if ( ! isset( $fail2ban_servers[ $server_slug ] ) ) {
	fwrite( STDERR, "Unknown server slug: {$server_slug}\n" );
	exit( 1 );
}

$server = $fail2ban_servers[ $server_slug ];
if ( empty( $server['name'] ) ) {
	fwrite( STDERR, "Server '{$server_slug}' is missing a 'name' field in FAIL2BAN_SERVERS.\n" );
	exit( 1 );
}

$all_accounts = defined( 'CLOUDFLARE_ACCOUNTS' ) ? CLOUDFLARE_ACCOUNTS : array();
$list_uuids   = array();
foreach ( $all_accounts as $account ) {
	if ( isset( $account['servers'] ) && ! in_array( $server_slug, $account['servers'], true ) ) {
		continue;
	}
	if ( empty( $account['id'] ) ) {
		continue;
	}
	$uuid = pw_get_fail2ban_list_uuid( $account['id'], CLOUDFLARE_API_KEY, CLOUDFLARE_EMAIL );
	if ( $uuid ) {
		$list_uuids[ $account['id'] ] = $uuid;
	}
}

$config_content = pw_generate_fail2ban_config( $server_slug, $server['name'], $list_uuids );

$output_rel = 'fail2ban-scripts/generated/cloudflare-fail2ban-config-' . $server_slug . '.txt';
$output_abs = $project_root . '/' . $output_rel;
$output_dir = dirname( $output_abs );

if ( ! is_dir( $output_dir ) && ! mkdir( $output_dir, 0755, true ) ) {
	fwrite( STDERR, "Failed to create output directory: {$output_dir}\n" );
	exit( 1 );
}

if ( false === file_put_contents( $output_abs, $config_content ) ) {
	fwrite( STDERR, "Failed to write config file: {$output_rel}\n" );
	exit( 1 );
}

echo $output_rel . PHP_EOL;
