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

// Create a backup of all DNS proxy statuses
function pw_create_proxy_backup( $account_ids, $api_key, $api_email ) {
	$backup_data = array(
		'timestamp' => time(),
		'date'      => gmdate( 'Y-m-d H:i:s' ),
		'zones'     => array(),
	);

	// Get all zones
	$zones = pw_get_cloudflare_zones( $account_ids, $api_key, $api_email );

	foreach ( $zones as $zone ) {
		$zone_id     = $zone['id'];
		$zone_name   = $zone['name'];
		$dns_records = pw_get_zone_dns_records( $zone_id, $api_key, $api_email );

		$zone_backup = array(
			'zone_id'   => $zone_id,
			'zone_name' => $zone_name,
			'records'   => array(),
		);

		foreach ( $dns_records as $record ) {
			$zone_backup['records'][] = array(
				'id'      => $record['id'],
				'type'    => $record['type'],
				'name'    => $record['name'],
				'content' => $record['content'],
				'proxied' => $record['proxied'],
			);
		}

		$backup_data['zones'][] = $zone_backup;
	}

	// Create backups directory if it doesn't exist
	$backup_dir = __DIR__ . '/backups';
	if ( ! is_dir( $backup_dir ) ) {
		mkdir( $backup_dir, 0755, true );
	}

	// Create filename with timestamp
	$filename = 'proxy_backup_' . gmdate( 'Y-m-d_H-i-s' ) . '.json';
	$filepath = $backup_dir . '/' . $filename;

	// Save backup
	$json_data = json_encode( $backup_data, JSON_PRETTY_PRINT );
	file_put_contents( $filepath, $json_data );

	return array(
		'success'  => true,
		'filename' => $filename,
		'filepath' => $filepath,
		'count'    => count( $backup_data['zones'] ),
	);
}

// Get list of available backups
function pw_get_proxy_backups() {
	$backup_dir = __DIR__ . '/backups';
	$backups    = array();

	if ( ! is_dir( $backup_dir ) ) {
		return $backups;
	}

	$files = glob( $backup_dir . '/proxy_backup_*.json' );

	foreach ( $files as $file ) {
		$filename = basename( $file );
		$data     = json_decode( file_get_contents( $file ), true );

		if ( $data && isset( $data['timestamp'] ) ) {
			$backups[] = array(
				'filename'  => $filename,
				'filepath'  => $file,
				'timestamp' => $data['timestamp'],
				'date'      => $data['date'],
				'zones'     => isset( $data['zones'] ) ? count( $data['zones'] ) : 0,
			);
		}
	}

	// Sort by timestamp, newest first
	usort(
		$backups,
		function ( $a, $b ) {
			return $b['timestamp'] - $a['timestamp'];
		}
	);

	return $backups;
}

// Get the most recent backup timestamp
function pw_get_latest_backup_time() {
	$backups = pw_get_proxy_backups();
	if ( empty( $backups ) ) {
		return 0;
	}
	return $backups[0]['timestamp'];
}

// Check if we can disable all (within 1 hour of last backup)
function pw_can_disable_all() {
	$latest_backup = pw_get_latest_backup_time();
	if ( $latest_backup === 0 ) {
		return false;
	}

	$one_hour_ago = time() - 3600;
	return $latest_backup >= $one_hour_ago;
}

// Restore from a backup file
function pw_restore_proxy_backup( $filename, $api_key, $api_email ) {
	$backup_dir = __DIR__ . '/backups';
	$filepath   = $backup_dir . '/' . basename( $filename );

	if ( ! file_exists( $filepath ) ) {
		return array(
			'success' => false,
			'message' => 'Backup file not found',
		);
	}

	$backup_data = json_decode( file_get_contents( $filepath ), true );

	if ( ! $backup_data || ! isset( $backup_data['zones'] ) ) {
		return array(
			'success' => false,
			'message' => 'Invalid backup file',
		);
	}

	$headers        = array(
		"X-Auth-Email: $api_email",
		"X-Auth-Key: $api_key",
		'Content-Type: application/json',
	);
	$updated_count  = 0;
	$error_count    = 0;
	$skipped_count  = 0;

	foreach ( $backup_data['zones'] as $zone_backup ) {
		$zone_id = $zone_backup['zone_id'];

		foreach ( $zone_backup['records'] as $record_backup ) {
			$record_id      = $record_backup['id'];
			$desired_status = $record_backup['proxied'];

			// Get current status
			$get_url      = "https://api.cloudflare.com/client/v4/zones/{$zone_id}/dns_records/{$record_id}";
			$get_response = pw_make_curl_request( $get_url, 'GET', $headers );

			if ( ! isset( $get_response['success'] ) || ! $get_response['success'] ) {
				$error_count++;
				continue;
			}

			$current_record = $get_response['result'];

			// Skip if already in desired state
			if ( $current_record['proxied'] === $desired_status ) {
				$skipped_count++;
				continue;
			}

			// Update the record
			$update_url  = "https://api.cloudflare.com/client/v4/zones/{$zone_id}/dns_records/{$record_id}";
			$update_data = array(
				'type'    => $record_backup['type'],
				'name'    => $record_backup['name'],
				'content' => $record_backup['content'],
				'proxied' => $desired_status,
			);

			if ( ! $desired_status ) {
				$update_data['ttl'] = 1;
			}

			$update_response = pw_make_curl_request( $update_url, 'PATCH', $headers, $update_data );

			if ( isset( $update_response['success'] ) && $update_response['success'] ) {
				$updated_count++;
			} else {
				$error_count++;
			}
		}
	}

	return array(
		'success' => true,
		'updated' => $updated_count,
		'skipped' => $skipped_count,
		'errors'  => $error_count,
	);
}

// Disable all proxy for all zones
function pw_disable_all_proxy( $account_ids, $api_key, $api_email ) {
	$zones = pw_get_cloudflare_zones( $account_ids, $api_key, $api_email );

	$headers       = array(
		"X-Auth-Email: $api_email",
		"X-Auth-Key: $api_key",
		'Content-Type: application/json',
	);
	$updated_count = 0;
	$error_count   = 0;

	foreach ( $zones as $zone ) {
		$zone_id     = $zone['id'];
		$dns_records = pw_get_zone_dns_records( $zone_id, $api_key, $api_email );

		foreach ( $dns_records as $record ) {
			// Only update if currently proxied
			if ( $record['proxied'] ) {
				$update_url  = "https://api.cloudflare.com/client/v4/zones/{$zone_id}/dns_records/{$record['id']}";
				$update_data = array(
					'type'    => $record['type'],
					'name'    => $record['name'],
					'content' => $record['content'],
					'proxied' => false,
					'ttl'     => 1,
				);

				$update_response = pw_make_curl_request( $update_url, 'PATCH', $headers, $update_data );

				if ( isset( $update_response['success'] ) && $update_response['success'] ) {
					$updated_count++;
				} else {
					$error_count++;
				}
			}
		}
	}

	return array(
		'success' => true,
		'updated' => $updated_count,
		'errors'  => $error_count,
	);
}

// Disable all proxy for a specific zone
function pw_disable_zone_proxy( $zone_id, $api_key, $api_email ) {
	$dns_records = pw_get_zone_dns_records( $zone_id, $api_key, $api_email );

	$headers       = array(
		"X-Auth-Email: $api_email",
		"X-Auth-Key: $api_key",
		'Content-Type: application/json',
	);
	$updated_count = 0;
	$error_count   = 0;

	foreach ( $dns_records as $record ) {
		// Only update if currently proxied
		if ( $record['proxied'] ) {
			$update_url  = "https://api.cloudflare.com/client/v4/zones/{$zone_id}/dns_records/{$record['id']}";
			$update_data = array(
				'type'    => $record['type'],
				'name'    => $record['name'],
				'content' => $record['content'],
				'proxied' => false,
				'ttl'     => 1,
			);

			$update_response = pw_make_curl_request( $update_url, 'PATCH', $headers, $update_data );

			if ( isset( $update_response['success'] ) && $update_response['success'] ) {
				$updated_count++;
			} else {
				$error_count++;
			}
		}
	}

	return array(
		'success' => true,
		'updated' => $updated_count,
		'errors'  => $error_count,
	);
}

// Restore proxy settings for a specific zone from backup
function pw_restore_zone_from_backup( $zone_id, $filename, $api_key, $api_email ) {
	$backup_dir = __DIR__ . '/backups';
	$filepath   = $backup_dir . '/' . basename( $filename );

	if ( ! file_exists( $filepath ) ) {
		return array(
			'success' => false,
			'message' => 'Backup file not found',
		);
	}

	$backup_data = json_decode( file_get_contents( $filepath ), true );

	if ( ! $backup_data || ! isset( $backup_data['zones'] ) ) {
		return array(
			'success' => false,
			'message' => 'Invalid backup file',
		);
	}

	// Find the zone in the backup
	$zone_backup = null;
	foreach ( $backup_data['zones'] as $zone ) {
		if ( $zone['zone_id'] === $zone_id ) {
			$zone_backup = $zone;
			break;
		}
	}

	if ( ! $zone_backup ) {
		return array(
			'success' => false,
			'message' => 'Zone not found in backup',
		);
	}

	$headers       = array(
		"X-Auth-Email: $api_email",
		"X-Auth-Key: $api_key",
		'Content-Type: application/json',
	);
	$updated_count = 0;
	$error_count   = 0;
	$skipped_count = 0;
	$records_state = array(); // Track final state of all records

	foreach ( $zone_backup['records'] as $record_backup ) {
		$record_id      = $record_backup['id'];
		$desired_status = $record_backup['proxied'];

		// Get current status
		$get_url      = "https://api.cloudflare.com/client/v4/zones/{$zone_id}/dns_records/{$record_id}";
		$get_response = pw_make_curl_request( $get_url, 'GET', $headers );

		if ( ! isset( $get_response['success'] ) || ! $get_response['success'] ) {
			$error_count++;
			continue;
		}

		$current_record = $get_response['result'];

		// Skip if already in desired state
		if ( $current_record['proxied'] === $desired_status ) {
			$skipped_count++;
			// Still add to records_state for UI update
			$records_state[] = array(
				'id'      => $record_id,
				'proxied' => $desired_status,
			);
			continue;
		}

		// Update the record
		$update_url  = "https://api.cloudflare.com/client/v4/zones/{$zone_id}/dns_records/{$record_id}";
		$update_data = array(
			'type'    => $record_backup['type'],
			'name'    => $record_backup['name'],
			'content' => $record_backup['content'],
			'proxied' => $desired_status,
		);

		if ( ! $desired_status ) {
			$update_data['ttl'] = 1;
		}

		$update_response = pw_make_curl_request( $update_url, 'PATCH', $headers, $update_data );

		if ( isset( $update_response['success'] ) && $update_response['success'] ) {
			$updated_count++;
			$records_state[] = array(
				'id'      => $record_id,
				'proxied' => $desired_status,
			);
		} else {
			$error_count++;
		}
	}

	return array(
		'success' => true,
		'updated' => $updated_count,
		'skipped' => $skipped_count,
		'errors'  => $error_count,
		'records' => $records_state,
	);
}

if ( ! function_exists( 'pre_r' ) ) :
	function pre_r( $array ) {
		print '<pre class="squarecandy-pre-r">';
		print_r( $array ); // phpcs:ignore
		print '</pre>';
	}
endif;
