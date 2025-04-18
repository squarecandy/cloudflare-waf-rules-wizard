<?php

// Unified function to make curl requests
function pw_make_curl_request( $url, $method, $headers, $data = null ) {
	$ch = curl_init();
	curl_setopt( $ch, CURLOPT_URL, $url );
	curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, $method );
	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
	curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );

	if ( $data ) {
		curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode( $data ) );
	}

	$response = curl_exec( $ch );
	if ( curl_errno( $ch ) ) {
		return 'Error:' . curl_error( $ch );
	}
	curl_close( $ch );
	return json_decode( $response, true );
}

// Get the list of all zones based on the account ID
function pw_get_cloudflare_zones( $account_ids, $api_key, $api_email ) {
	$all_zones = array(); // Array to hold all zones
	$per_page  = 50; // The maximum items per page you can request from the Cloudflare API for this endpoint

	foreach ( $account_ids as $account_id ) {
		$page = 1;
		do {
			$url      = "https://api.cloudflare.com/client/v4/zones?account.id=$account_id&page=$page&per_page=$per_page";
			$headers  = array(
				"X-Auth-Email: $api_email",
				"X-Auth-Key: $api_key",
				'Content-Type: application/json',
			);
			$response = pw_make_curl_request( $url, 'GET', $headers );

			if ( isset( $response['result'] ) ) {
				// Merge the retrieved zones into the allZones array
				$all_zones = array_merge( $all_zones, $response['result'] );
			}

			// Check if there are more pages to fetch
			$total_pages = isset( $response['result_info']['total_pages'] ) ? (int) $response['result_info']['total_pages'] : 1;
			$page++;

		} while ( $page <= $total_pages );
	}

	return $all_zones;
}

function pw_cloudflare_ruleset_manager_process_zones( $rules = array() ) {
	$email       = CLOUDFLARE_EMAIL;
	$api_key     = CLOUDFLARE_API_KEY;
	$account_ids = CLOUDFLARE_ACCOUNT_IDS;
	$zone_ids    = isset( $_POST['pw_zone_ids'] ) ? $_POST['pw_zone_ids'] : array();

	if ( empty( $zone_ids ) ) {
		echo '<div class="notice notice-error"><p>Please enter all the required fields.</p></div>';
		return;
	}

	if ( empty( $rules ) ) {
		echo '<div class="notice notice-error"><p>Please make sure a valid rule set is selected.</p></div>';
		return;
	}

	$headers = array(
		"X-Auth-Email: $email",
		"X-Auth-Key: $api_key",
		'Content-Type: application/json',
	);

	function pw_get_ruleset_id( $zone_id, $headers ) {
		$url      = "https://api.cloudflare.com/client/v4/zones/{$zone_id}/rulesets";
		$response = pw_make_curl_request( $url, 'GET', $headers );

		if ( ! empty( $response['result'] ) ) {
			foreach ( $response['result'] as $ruleset ) {
				if ( 'zone' === $ruleset['kind'] && 'http_request_firewall_custom' === $ruleset['phase'] ) {
					return $ruleset['id'];
				}
			}
		}
		return null;
	}

	function pw_create_ruleset( $zone_id, $headers ) {
		$url  = "https://api.cloudflare.com/client/v4/zones/{$zone_id}/rulesets";
		$data = array(
			'name'  => 'Custom ruleset for http_request_firewall_custom phase',
			'kind'  => 'zone',
			'phase' => 'http_request_firewall_custom',
		);

		$response = pw_make_curl_request( $url, 'POST', $headers, $data );
		return $response['result']['id'] ?? null;
	}

	function pw_replace_ruleset( $zone_id, $ruleset_id, $headers, $rules ) {
		$url      = "https://api.cloudflare.com/client/v4/zones/{$zone_id}/rulesets/{$ruleset_id}";
		$data     = array(
			'rules' => $rules,
		);
		$response = pw_make_curl_request( $url, 'PUT', $headers, $data );
		return $response;
	}

	$zones = pw_get_cloudflare_zones( $account_ids, $api_key, $email );

	foreach ( $zone_ids as $zone_id ) {
		$zone_name = '';
		foreach ( $zones as $zone ) {
			if ( $zone['id'] === $zone_id ) {
				$zone_name = $zone['name'];
				// escape the zone name for HTML output
				$zone_name = htmlspecialchars( $zone_name, ENT_QUOTES, 'UTF-8' );
				break;
			}
		}

		$ruleset_id = pw_get_ruleset_id( $zone_id, $headers );

		if ( ! $ruleset_id ) {
			$ruleset_id = pw_create_ruleset( $zone_id, $headers );
			if ( ! $ruleset_id ) {
				echo '<div class="notice notice-error"><p>Failed to create ruleset for domain: ' . $zone_name . '</p></div>';
				continue;
			}
		}

		$response = pw_replace_ruleset( $zone_id, $ruleset_id, $headers, $rules );
		if ( isset( $response['success'] ) && $response['success'] ) {
			echo '<div class="notice notice-success"><p>Successfully updated ruleset for domain: ' . $zone_name . '</p></div>';
		} else {
			$error_message = isset( $response['errors'][0]['message'] ) ? $response['errors'][0]['message'] : 'Unknown error';
			$error_message = htmlspecialchars( $error_message, ENT_QUOTES, 'UTF-8' );
			echo '<div class="notice notice-error"><p>Failed to update ruleset for domain: ' . $zone_name . '. Error: ' . $error_message . '</p></div>';
		}
	}
}


if ( ! function_exists( 'pre_r' ) ) :
	function pre_r( $array ) {
		print '<pre class="squarecandy-pre-r">';
		print_r( $array ); // phpcs:ignore
		print '</pre>';
	}
endif;
