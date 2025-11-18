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
