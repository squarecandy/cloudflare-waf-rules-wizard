<?php
/**
 * AJAX Handler for Security Status Updates
 */

// Prevent direct access
if ( ! isset( $_SERVER['HTTP_X_REQUESTED_WITH'] ) || strtolower( $_SERVER['HTTP_X_REQUESTED_WITH'] ) !== 'xmlhttprequest' ) {
	die( 'Direct access not permitted' );
}

require_once 'config.php';
require_once 'functions.php';
require_once 'cache.php';

// Security check - make sure we have all needed Cloudflare credentials
if (
	! defined( 'CLOUDFLARE_API_KEY' ) ||
	! defined( 'CLOUDFLARE_EMAIL' ) ||
	! defined( 'CLOUDFLARE_ACCOUNT_IDS' ) ||
	empty( CLOUDFLARE_API_KEY ) ||
	empty( CLOUDFLARE_EMAIL ) ||
	empty( CLOUDFLARE_ACCOUNT_IDS ) ||
	! is_array( CLOUDFLARE_ACCOUNT_IDS )
) {
	echo json_encode(
		array(
			'success' => false,
			'message' => 'Missing Cloudflare API credentials',
		)
	);
	exit;
}

// Check for required parameters
if ( empty( $_POST['setting'] ) ) {
	echo json_encode(
		array(
			'success' => false,
			'message' => 'Missing required parameters',
		)
	);
	exit;
}

$setting = $_POST['setting'];

// --- Cache refresh actions ---

// Regenerate nginx conf files from rules.php (no API call needed).
if ( 'refresh_nginx_rules' === $setting ) {
	require_once 'generate-nginx-rules.php';
	$file_standard = __DIR__ . '/nginx/bot-blocking.conf';
	if ( file_exists( $file_standard ) ) {
		echo json_encode(
			array(
				'success'      => true,
				'generated_at' => date( 'Y-m-d H:i:s', filemtime( $file_standard ) ),
				'message'      => 'Nginx conf files regenerated.',
			)
		);
	} else {
		echo json_encode(
			array(
				'success' => false,
				'message' => 'Regeneration failed — output file not found.',
			)
		);
	}
	exit;
}

// Refresh zones list (used by WAF Rules, Security Status, Proxy Status pages).
if ( 'refresh_zones' === $setting ) {
	$zones = pw_get_cloudflare_zones(
		CLOUDFLARE_ACCOUNT_IDS,
		CLOUDFLARE_API_KEY,
		CLOUDFLARE_EMAIL
	);
	if ( is_array( $zones ) ) {
		pw_cache_set( 'zones', $zones );
		echo json_encode(
			array(
				'success' => true,
				'count'   => count( $zones ),
				'message' => 'Zones refreshed (' . count( $zones ) . ' domains loaded).',
			)
		);
	} else {
		echo json_encode(
			array(
				'success' => false,
				'message' => 'Failed to fetch zones from Cloudflare API.',
			)
		);
	}
	exit;
}

// Refresh security status for all zones.
if ( 'refresh_security_status' === $setting ) {
	$zones = pw_cache_get( 'zones' );
	if ( ! $zones ) {
		$zones = pw_get_cloudflare_zones(
			CLOUDFLARE_ACCOUNT_IDS,
			CLOUDFLARE_API_KEY,
			CLOUDFLARE_EMAIL
		);
		if ( is_array( $zones ) ) {
			pw_cache_set( 'zones', $zones );
		}
	}

	if ( ! is_array( $zones ) || empty( $zones ) ) {
		echo json_encode(
			array(
				'success' => false,
				'message' => 'No zones available to refresh.',
			)
		);
		exit;
	}

	$security_data = array();
	foreach ( $zones as $zone ) {
		$security_data[ $zone['id'] ] = pw_get_zone_security_settings(
			$zone['id'],
			CLOUDFLARE_API_KEY,
			CLOUDFLARE_EMAIL
		);
	}
	pw_cache_set( 'security_status', $security_data );
	echo json_encode(
		array(
			'success' => true,
			'count'   => count( $security_data ),
			'message' => 'Security status refreshed for ' . count( $security_data ) . ' zones.',
		)
	);
	exit;
}

// Refresh DNS proxy records for all zones.
if ( 'refresh_proxy_status' === $setting ) {
	$zones = pw_cache_get( 'zones' );
	if ( ! $zones ) {
		$zones = pw_get_cloudflare_zones(
			CLOUDFLARE_ACCOUNT_IDS,
			CLOUDFLARE_API_KEY,
			CLOUDFLARE_EMAIL
		);
		if ( is_array( $zones ) ) {
			pw_cache_set( 'zones', $zones );
		}
	}

	if ( ! is_array( $zones ) || empty( $zones ) ) {
		echo json_encode(
			array(
				'success' => false,
				'message' => 'No zones available to refresh.',
			)
		);
		exit;
	}

	$proxy_data = array();
	foreach ( $zones as $zone ) {
		$proxy_data[ $zone['id'] ] = pw_get_zone_dns_records(
			$zone['id'],
			CLOUDFLARE_API_KEY,
			CLOUDFLARE_EMAIL
		);
	}
	pw_cache_set( 'proxy_status', $proxy_data );
	echo json_encode(
		array(
			'success' => true,
			'count'   => count( $proxy_data ),
			'message' => 'DNS proxy records refreshed for ' . count( $proxy_data ) . ' zones.',
		)
	);
	exit;
}

// --- End cache refresh actions ---

// Handle backup creation
if ( $setting === 'create_backup' ) {
	$result = pw_create_proxy_backup(
		CLOUDFLARE_ACCOUNT_IDS,
		CLOUDFLARE_API_KEY,
		CLOUDFLARE_EMAIL
	);

	if ( $result['success'] ) {
		$result['message'] = "Backup created successfully: {$result['filename']} ({$result['count']} zones)";
	} else {
		$result['message'] = 'Failed to create backup';
	}

	echo json_encode( $result );
	exit;
}

// Handle getting backup list
if ( $setting === 'get_backups' ) {
	$backups            = pw_get_proxy_backups();
	$can_disable_all    = pw_can_disable_all();
	$latest_backup_time = pw_get_latest_backup_time();

	echo json_encode(
		array(
			'success'            => true,
			'backups'            => $backups,
			'can_disable_all'    => $can_disable_all,
			'latest_backup_time' => $latest_backup_time,
		)
	);
	exit;
}

// Handle restore from backup
if ( $setting === 'restore_backup' ) {
	if ( empty( $_POST['filename'] ) ) {
		echo json_encode(
			array(
				'success' => false,
				'message' => 'Missing backup filename',
			)
		);
		exit;
	}

	$result = pw_restore_proxy_backup(
		$_POST['filename'],
		CLOUDFLARE_API_KEY,
		CLOUDFLARE_EMAIL
	);

	if ( $result['success'] ) {
		$message = "Restored {$result['updated']} record(s)";
		if ( $result['skipped'] > 0 ) {
			$message .= ", {$result['skipped']} already correct";
		}
		if ( $result['errors'] > 0 ) {
			$message .= ", {$result['errors']} error(s)";
		}
		$result['message'] = $message;
	}

	echo json_encode( $result );
	exit;
}

// Handle disable all
if ( $setting === 'disable_all' ) {
	// Check if we can disable all
	if ( ! pw_can_disable_all() ) {
		echo json_encode(
			array(
				'success' => false,
				'message' => 'Cannot disable all: A backup must be created within the last hour',
			)
		);
		exit;
	}

	$result = pw_disable_all_proxy(
		CLOUDFLARE_ACCOUNT_IDS,
		CLOUDFLARE_API_KEY,
		CLOUDFLARE_EMAIL
	);

	if ( $result['success'] ) {
		$message = "Disabled proxy for {$result['updated']} record(s)";
		if ( $result['errors'] > 0 ) {
			$message .= ", {$result['errors']} error(s)";
		}
		$result['message'] = $message;
	}

	echo json_encode( $result );
	exit;
}

// Handle disable all for a specific zone
if ( $setting === 'disable_zone' ) {
	if ( empty( $_POST['zone_id'] ) ) {
		echo json_encode(
			array(
				'success' => false,
				'message' => 'Missing zone ID',
			)
		);
		exit;
	}

	// Check if we can disable (backup within 1 hour)
	if ( ! pw_can_disable_all() ) {
		echo json_encode(
			array(
				'success' => false,
				'message' => 'Cannot disable: A backup must be created within the last hour',
			)
		);
		exit;
	}

	$zone_id = $_POST['zone_id'];
	$result  = pw_disable_zone_proxy(
		$zone_id,
		CLOUDFLARE_API_KEY,
		CLOUDFLARE_EMAIL
	);

	if ( $result['success'] ) {
		$message = "Disabled proxy for {$result['updated']} record(s)";
		if ( $result['errors'] > 0 ) {
			$message .= ", {$result['errors']} error(s)";
		}
		$result['message'] = $message;
	}

	echo json_encode( $result );
	exit;
}

// Handle restore for a specific zone
if ( $setting === 'restore_zone' ) {
	if ( empty( $_POST['zone_id'] ) || empty( $_POST['filename'] ) ) {
		echo json_encode(
			array(
				'success' => false,
				'message' => 'Missing zone ID or backup filename',
			)
		);
		exit;
	}

	$zone_id = $_POST['zone_id'];
	$result  = pw_restore_zone_from_backup(
		$zone_id,
		$_POST['filename'],
		CLOUDFLARE_API_KEY,
		CLOUDFLARE_EMAIL
	);

	if ( $result['success'] ) {
		$message = "Restored {$result['updated']} record(s)";
		if ( $result['skipped'] > 0 ) {
			$message .= ", {$result['skipped']} already correct";
		}
		if ( $result['errors'] > 0 ) {
			$message .= ", {$result['errors']} error(s)";
		}
		$result['message'] = $message;
	}

	echo json_encode( $result );
	exit;
}

// --- Fail2ban Integration Actions ---

if ( $setting === 'fail2ban_check_list_status' ) {
	$account_id  = isset( $_POST['account_id'] ) ? trim( $_POST['account_id'] ) : '';
	$account_idx = isset( $_POST['account_idx'] ) ? (int) $_POST['account_idx'] : 0;

	// Validate account_id against the configured set to prevent SSRF with our global key.
	if ( ! in_array( $account_id, CLOUDFLARE_ACCOUNT_IDS, true ) ) {
		echo json_encode(
			array(
				'success' => false,
				'message' => 'Invalid account ID',
			)
		);
		exit;
	}

	$list_exists = pw_fail2ban_list_exists( $account_id, CLOUDFLARE_API_KEY, CLOUDFLARE_EMAIL );

	$token_path        = defined( 'FAIL2BAN_TOKEN_PATH' ) ? FAIL2BAN_TOKEN_PATH : '';
	$slug              = pw_get_account_slug( $account_idx );
	$token_file        = rtrim( $token_path, '/' ) . '/cloudflare-api-key-' . $slug;
	$token_file_exists = ! empty( $token_path ) && file_exists( $token_file );

	echo json_encode(
		array(
			'success'           => true,
			'list_exists'       => $list_exists,
			'token_file_exists' => $token_file_exists,
		)
	);
	exit;
}

if ( $setting === 'fail2ban_create_list' ) {
	$account_id = isset( $_POST['account_id'] ) ? trim( $_POST['account_id'] ) : '';

	if ( ! in_array( $account_id, CLOUDFLARE_ACCOUNT_IDS, true ) ) {
		echo json_encode(
			array(
				'success' => false,
				'message' => 'Invalid account ID',
			)
		);
		exit;
	}

	$result = pw_create_account_list( $account_id, CLOUDFLARE_API_KEY, CLOUDFLARE_EMAIL );
	echo json_encode( $result );
	exit;
}

if ( $setting === 'fail2ban_create_token' ) {
	$account_id  = isset( $_POST['account_id'] ) ? trim( $_POST['account_id'] ) : '';
	$account_idx = isset( $_POST['account_idx'] ) ? (int) $_POST['account_idx'] : 0;

	if ( ! in_array( $account_id, CLOUDFLARE_ACCOUNT_IDS, true ) ) {
		echo json_encode(
			array(
				'success' => false,
				'message' => 'Invalid account ID',
			)
		);
		exit;
	}

	$result = pw_create_fail2ban_token( $account_id, $account_idx, CLOUDFLARE_API_KEY, CLOUDFLARE_EMAIL );

	// SECURITY: NEVER return the token value. Return only success status and file path.
	if ( $result['success'] ) {
		echo json_encode(
			array(
				'success'  => true,
				'filepath' => $result['filepath'],
			)
		);
	} else {
		echo json_encode(
			array(
				'success' => false,
				'message' => $result['message'],
			)
		);
	}
	exit;
}

if ( $setting === 'fail2ban_download_config' ) {
	$server_name = isset( $_POST['server_name'] ) ? trim( $_POST['server_name'] ) : '';

	// Validate against the configured server list to prevent arbitrary name injection.
	$fail2ban_servers = defined( 'FAIL2BAN_SERVERS' ) ? FAIL2BAN_SERVERS : array();
	$matched_server   = null;
	$matched_slug     = '';
	foreach ( $fail2ban_servers as $slug => $server ) {
		if ( isset( $server['name'] ) && $server['name'] === $server_name ) {
			$matched_server = $server;
			$matched_slug   = $slug;
			break;
		}
	}

	// Fetch the list UUID for each account on this server — cached in the config so scripts
	// don't need an extra API call per ban event.
	if ( null === $matched_server ) {
		echo json_encode(
			array(
				'success' => false,
				'message' => 'Invalid server name',
			)
		);
		exit;
	}

	$all_accounts = defined( 'CLOUDFLARE_ACCOUNTS' ) ? CLOUDFLARE_ACCOUNTS : array();
	$list_uuids   = array();
	foreach ( $all_accounts as $account ) {
		if ( isset( $account['servers'] ) && ! in_array( $matched_slug, $account['servers'], true ) ) {
			continue;
		}
		$uuid = pw_get_fail2ban_list_uuid( $account['id'], CLOUDFLARE_API_KEY, CLOUDFLARE_EMAIL );
		if ( $uuid ) {
			$list_uuids[ $account['id'] ] = $uuid;
		}
	}

	$config = pw_generate_fail2ban_config( $matched_slug, $server_name, $list_uuids );
	echo json_encode(
		array(
			'success'  => true,
			'config'   => $config,
			'filename' => 'cloudflare-fail2ban-config.txt',
		)
	);
	exit;
}

// --- End Fail2ban Actions ---

// For other settings, require zone_id and value
if ( empty( $_POST['zone_id'] ) || ! isset( $_POST['value'] ) ) {
	echo json_encode(
		array(
			'success' => false,
			'message' => 'Missing required parameters',
		)
	);
	exit;
}

$zone_id   = $_POST['zone_id'];
$new_value = $_POST['value'] === 'true';

// Prepare API request headers
$headers = array(
	'X-Auth-Email: ' . CLOUDFLARE_EMAIL,
	'X-Auth-Key: ' . CLOUDFLARE_API_KEY,
	'Content-Type: application/json',
);

$result = array(
	'success'   => false,
	'message'   => 'Unknown error',
	'new_value' => $new_value ? 'On' : 'Off',
);

// Define the base URL for all settings (same for all cases)
$url = "https://api.cloudflare.com/client/v4/zones/{$zone_id}/bot_management";

// Handle individual DNS record proxy toggle
if ( $setting === 'dns_record_proxy' ) {
	if ( empty( $_POST['record_id'] ) || empty( $_POST['record_type'] ) || empty( $_POST['record_name'] ) || empty( $_POST['record_content'] ) ) {
		echo json_encode(
			array(
				'success' => false,
				'message' => 'Missing required DNS record parameters',
			)
		);
		exit;
	}

	$record_id      = $_POST['record_id'];
	$record_type    = $_POST['record_type'];
	$record_name    = $_POST['record_name'];
	$record_content = $_POST['record_content'];
	$enable_proxy   = ( $_POST['value'] === 'true' );

	$update_url  = "https://api.cloudflare.com/client/v4/zones/{$zone_id}/dns_records/{$record_id}";
	$update_data = array(
		'type'    => $record_type,
		'name'    => $record_name,
		'content' => $record_content,
		'proxied' => $enable_proxy,
	);

	// Add TTL if not proxied (required when proxied is false)
	if ( ! $enable_proxy ) {
		$update_data['ttl'] = 1; // 1 = automatic
	}

	$response = pw_make_curl_request( $update_url, 'PATCH', $headers, $update_data );

	if ( isset( $response['success'] ) && $response['success'] === true ) {
		$result = array(
			'success' => true,
			'message' => 'Proxy ' . ( $enable_proxy ? 'enabled' : 'disabled' ) . ' for ' . $record_name,
		);
	} else {
		$error_message = isset( $response['errors'][0]['message'] ) ? $response['errors'][0]['message'] : 'Unknown error';
		$result        = array(
			'success' => false,
			'message' => 'API Error: ' . $error_message,
		);
	}

	echo json_encode( $result );
	exit;
}

// Handle proxy status separately (bulk zone toggle - deprecated, kept for backwards compatibility)
if ( $setting === 'proxy_status' ) {
	$enable_proxy = ( $_POST['value'] === 'on' );

	$proxy_result = pw_toggle_zone_proxy_status(
		$zone_id,
		CLOUDFLARE_API_KEY,
		CLOUDFLARE_EMAIL,
		$enable_proxy
	);

	if ( $proxy_result['success'] ) {
		// Get the updated status after toggling
		$new_status = pw_get_zone_proxy_status(
			$zone_id,
			CLOUDFLARE_API_KEY,
			CLOUDFLARE_EMAIL
		);

		// Determine CSS class
		$status_class = 'unknown';
		if ( strpos( $new_status, 'All On' ) !== false ) {
			$status_class = 'on';
		} elseif ( strpos( $new_status, 'All Off' ) !== false ) {
			$status_class = 'off';
		} elseif ( strpos( $new_status, 'Mixed' ) !== false ) {
			$status_class = 'mixed';
		}

		$message = 'Proxy status updated';
		if ( $proxy_result['updated'] > 0 ) {
			$message .= ": {$proxy_result['updated']} record(s) updated";
		}
		if ( $proxy_result['errors'] > 0 ) {
			$message .= ", {$proxy_result['errors']} error(s)";
		}

		$result = array(
			'success'      => true,
			'message'      => $message,
			'new_value'    => $new_status,
			'status_class' => $status_class,
		);
	} else {
		$result = array(
			'success' => false,
			'message' => $proxy_result['message'] ?? 'Failed to update proxy status',
		);
	}

	echo json_encode( $result );
	exit;
}

// Define setting mappings
$settings_map = array(
	'bot_fight_mode'       => array(
		'param'       => 'fight_mode',
		'value_true'  => true,
		'value_false' => false,
	),
	'block_ai_bots'        => array(
		'param'       => 'ai_bots_protection',
		'value_true'  => 'block',
		'value_false' => 'disabled',
	),
	'ai_labyrinth'         => array(
		'param'       => 'crawler_protection',
		'value_true'  => 'enabled',
		'value_false' => 'disabled',
	),
	'javascript_detection' => array(
		'param'       => 'enable_js',
		'value_true'  => true,
		'value_false' => false,
	),
	'robots_management'    => array(
		'param'       => 'is_robots_txt_managed',
		'value_true'  => true,
		'value_false' => false,
	),
);

// Check if the setting exists in our mapping
if ( ! isset( $settings_map[ $setting ] ) ) {
	$result['message'] = 'Unknown setting';
	echo json_encode( $result );
	exit;
}

// Prepare data payload based on setting mapping
$setting_config = $settings_map[ $setting ];
$param_value    = $new_value ? $setting_config['value_true'] : $setting_config['value_false'];
$data           = array( $setting_config['param'] => $param_value );

// Make the API request
$response = pw_make_curl_request( $url, 'PUT', $headers, $data );

// Check response
if ( isset( $response['success'] ) && $response['success'] === true ) {
	$result['success'] = true;
	$result['message'] = ucfirst( $setting ) . ' has been ' . ( $new_value ? 'enabled' : 'disabled' );
} else {
	$error_message     = isset( $response['errors'][0]['message'] ) ? $response['errors'][0]['message'] : 'Unknown error';
	$result['message'] = 'API Error: ' . $error_message;
}

// Return JSON response
echo json_encode( $result );
