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

// New function to get security feature status for a zone
function pw_get_zone_security_settings( $zone_id, $api_key, $api_email ) {
	$settings = array(
		'bot_fight_mode'       => 'Unknown',
		'block_ai_bots'        => 'Unknown',
		'ai_labyrinth'         => 'Unknown',
		'robots_management'    => 'Unknown',
		'javascript_detection' => 'Unknown',
	);

	$headers = array(
		"X-Auth-Email: $api_email",
		"X-Auth-Key: $api_key",
		'Content-Type: application/json',
	);

	// Bot Management API call
	$url      = "https://api.cloudflare.com/client/v4/zones/{$zone_id}/bot_management";
	$response = pw_make_curl_request( $url, 'GET', $headers );

	// Error checking
	if ( ! isset( $response['success'] ) || true !== $response['success'] ) {
		return $settings;
	}

	if ( isset( $response['result'] ) ) {
		$result = $response['result'];

		// Bot Fight Mode
		$settings['bot_fight_mode'] = isset( $result['fight_mode'] ) && ! empty( $result['fight_mode'] )
			? 'On' : 'Off';

		// AI Bots Protection
		if ( isset( $result['ai_bots_protection'] ) ) {
			if ( 'block' === $result['ai_bots_protection'] ) {
				$settings['block_ai_bots'] = 'On';
			} elseif ( 'log' === $result['ai_bots_protection'] ) {
				$settings['block_ai_bots'] = 'Log Only';
			} else {
				$settings['block_ai_bots'] = 'Off';
			}
		}

		// AI Labyrinth (Crawler Protection)
		if ( isset( $result['crawler_protection'] ) ) {
			if ( 'enabled' === $result['crawler_protection'] ) {
				$settings['ai_labyrinth'] = 'On';
			} else {
				$settings['ai_labyrinth'] = 'Off';
			}
		}

		// Robots.txt Management
		$settings['robots_management'] = isset( $result['is_robots_txt_managed'] ) && $result['is_robots_txt_managed']
			? 'On' : 'Off';

		// JavaScript Detection
		if ( isset( $result['enable_js'] ) ) {
			$settings['javascript_detection'] = ! empty( $result['enable_js'] ) ? 'On' : 'Off';
		}
	}

	return $settings;
}

// Get DNS proxy status for a zone (checks if any DNS records have proxy enabled)
function pw_get_zone_proxy_status( $zone_id, $api_key, $api_email ) {
	$headers = array(
		"X-Auth-Email: $api_email",
		"X-Auth-Key: $api_key",
		'Content-Type: application/json',
	);

	$per_page      = 100;
	$page          = 1;
	$proxied_count = 0;
	$total_count   = 0;

	do {
		$url      = "https://api.cloudflare.com/client/v4/zones/{$zone_id}/dns_records?per_page={$per_page}&page={$page}";
		$response = pw_make_curl_request( $url, 'GET', $headers );

		if ( ! isset( $response['success'] ) || true !== $response['success'] ) {
			return 'Unknown';
		}

		if ( isset( $response['result'] ) && is_array( $response['result'] ) ) {
			foreach ( $response['result'] as $record ) {
				// Only count proxiable record types
				if ( in_array( $record['type'], array( 'A', 'AAAA', 'CNAME' ), true ) ) {
					$total_count++;
					if ( isset( $record['proxied'] ) && $record['proxied'] ) {
						$proxied_count++;
					}
				}
			}
		}

		$total_pages = isset( $response['result_info']['total_pages'] ) ? (int) $response['result_info']['total_pages'] : 1;
		$page++;

	} while ( $page <= $total_pages );

	if ( 0 === $total_count ) {
		return 'No Records';
	}

	if ( $proxied_count === $total_count ) {
		return 'All On';
	} elseif ( $proxied_count > 0 ) {
		return "Mixed ({$proxied_count}/{$total_count})";
	} else {
		return 'All Off';
	}
}

// Get all proxiable DNS records (A, AAAA, CNAME) for a zone
function pw_get_zone_dns_records( $zone_id, $api_key, $api_email ) {
	$headers = array(
		"X-Auth-Email: $api_email",
		"X-Auth-Key: $api_key",
		'Content-Type: application/json',
	);

	$per_page    = 100;
	$page        = 1;
	$all_records = array();

	do {
		$url      = "https://api.cloudflare.com/client/v4/zones/{$zone_id}/dns_records?per_page={$per_page}&page={$page}";
		$response = pw_make_curl_request( $url, 'GET', $headers );

		if ( ! isset( $response['success'] ) || true !== $response['success'] ) {
			return array();
		}

		if ( isset( $response['result'] ) && is_array( $response['result'] ) ) {
			foreach ( $response['result'] as $record ) {
				// Only include proxiable record types
				if ( in_array( $record['type'], array( 'A', 'AAAA', 'CNAME' ), true ) ) {
					$all_records[] = array(
						'id'      => $record['id'],
						'type'    => $record['type'],
						'name'    => $record['name'],
						'content' => $record['content'],
						'proxied' => isset( $record['proxied'] ) ? $record['proxied'] : false,
						'ttl'     => isset( $record['ttl'] ) ? $record['ttl'] : 1,
					);
				}
			}
		}

		$total_pages = isset( $response['result_info']['total_pages'] ) ? (int) $response['result_info']['total_pages'] : 1;
		$page++;

	} while ( $page <= $total_pages );

	return $all_records;
}

// Toggle proxy status for all DNS records in a zone
function pw_toggle_zone_proxy_status( $zone_id, $api_key, $api_email, $enable_proxy ) {
	$headers = array(
		"X-Auth-Email: $api_email",
		"X-Auth-Key: $api_key",
		'Content-Type: application/json',
	);

	$per_page       = 100;
	$page           = 1;
	$updated_count  = 0;
	$error_count    = 0;

	do {
		$url      = "https://api.cloudflare.com/client/v4/zones/{$zone_id}/dns_records?per_page={$per_page}&page={$page}";
		$response = pw_make_curl_request( $url, 'GET', $headers );

		if ( ! isset( $response['success'] ) || true !== $response['success'] ) {
			return array(
				'success' => false,
				'message' => 'Failed to fetch DNS records',
			);
		}

		if ( isset( $response['result'] ) && is_array( $response['result'] ) ) {
			foreach ( $response['result'] as $record ) {
				// Only update proxiable record types
				if ( in_array( $record['type'], array( 'A', 'AAAA', 'CNAME' ), true ) ) {
					// Only update if the current state is different
					if ( $record['proxied'] !== $enable_proxy ) {
						$update_url  = "https://api.cloudflare.com/client/v4/zones/{$zone_id}/dns_records/{$record['id']}";
						$update_data = array(
							'type'    => $record['type'],
							'name'    => $record['name'],
							'content' => $record['content'],
							'proxied' => $enable_proxy,
						);

						// Add TTL if not proxied
						if ( ! $enable_proxy && isset( $record['ttl'] ) ) {
							$update_data['ttl'] = $record['ttl'];
						}

						$update_response = pw_make_curl_request( $update_url, 'PATCH', $headers, $update_data );

						if ( isset( $update_response['success'] ) && $update_response['success'] ) {
							$updated_count++;
						} else {
							$error_count++;
						}
					}
				}
			}
		}

		$total_pages = isset( $response['result_info']['total_pages'] ) ? (int) $response['result_info']['total_pages'] : 1;
		$page++;

	} while ( $page <= $total_pages );

	return array(
		'success' => true,
		'updated' => $updated_count,
		'errors'  => $error_count,
	);
}

if ( ! function_exists( 'pre_r' ) ) :
	function pre_r( $array ) {
		print '<pre class="squarecandy-pre-r">';
		print_r( $array ); // phpcs:ignore
		print '</pre>';
	}
endif;
