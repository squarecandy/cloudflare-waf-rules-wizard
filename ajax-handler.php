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
if ( empty( $_POST['zone_id'] ) || empty( $_POST['setting'] ) || ! isset( $_POST['value'] ) ) {
	echo json_encode(
		array(
			'success' => false,
			'message' => 'Missing required parameters',
		)
	);
	exit;
}

$zone_id   = $_POST['zone_id'];
$setting   = $_POST['setting'];
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
