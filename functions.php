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

// Get existing WAF rules for a zone
function pw_get_existing_waf_rules( $zone_id, $api_key, $api_email ) {
	$headers = array(
		"X-Auth-Email: $api_email",
		"X-Auth-Key: $api_key",
		'Content-Type: application/json',
	);

	// Get ruleset ID
	$url      = "https://api.cloudflare.com/client/v4/zones/{$zone_id}/rulesets";
	$response = pw_make_curl_request( $url, 'GET', $headers );

	if ( empty( $response['result'] ) ) {
		return array();
	}

	$ruleset_id = null;
	foreach ( $response['result'] as $ruleset ) {
		if ( 'zone' === $ruleset['kind'] && 'http_request_firewall_custom' === $ruleset['phase'] ) {
			$ruleset_id = $ruleset['id'];
			break;
		}
	}

	if ( ! $ruleset_id ) {
		return array(); // No custom WAF rules exist
	}

	// Get the rules from the ruleset
	$rules_url      = "https://api.cloudflare.com/client/v4/zones/{$zone_id}/rulesets/{$ruleset_id}";
	$rules_response = pw_make_curl_request( $rules_url, 'GET', $headers );

	if ( isset( $rules_response['result']['rules'] ) ) {
		return $rules_response['result']['rules'];
	}

	return array();
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

// Returns true if a rule description ends with [CUSTOM] (case-insensitive).
function pw_is_custom_rule( $rule ) {
	$desc = isset( $rule['description'] ) ? trim( $rule['description'] ) : '';
	return (bool) preg_match( '/\[custom\]$/i', $desc );
}

// Strips Cloudflare read-only fields from a rule so it can be re-submitted in a PUT payload.
function pw_strip_readonly_rule_fields( $rule ) {
	$readonly_fields = array( 'id', 'version', 'last_updated', 'ref', 'last_updated_index' );
	foreach ( $readonly_fields as $field ) {
		unset( $rule[ $field ] );
	}
	return $rule;
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

		// Preserve any existing [CUSTOM] rules by prepending them before the API-managed rules.
		$existing_rules  = pw_get_existing_waf_rules( $zone_id, $api_key, $email );
		$preserved_rules = array();
		foreach ( $existing_rules as $existing_rule ) {
			if ( pw_is_custom_rule( $existing_rule ) ) {
				$preserved_rules[] = pw_strip_readonly_rule_fields( $existing_rule );
			}
		}
		$merged_rules = array_values( array_merge( $preserved_rules, $rules ) );

		$response = pw_replace_ruleset( $zone_id, $ruleset_id, $headers, $merged_rules );
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

	$per_page      = 100;
	$page          = 1;
	$updated_count = 0;
	$error_count   = 0;

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

	$headers       = array(
		"X-Auth-Email: $api_email",
		"X-Auth-Key: $api_key",
		'Content-Type: application/json',
	);
	$updated_count = 0;
	$error_count   = 0;
	$skipped_count = 0;

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

// =============================================================================
// Fail2ban Integration Functions
// =============================================================================

// Get the account name for a Cloudflare account ID.
// Return a URL-safe slug for a Cloudflare account based on its nickname in CLOUDFLARE_ACCOUNT_NAMES.
// Falls back to "account{N}" if no name is configured.
function pw_get_account_slug( $account_idx ) {
	$names = defined( 'CLOUDFLARE_ACCOUNT_NAMES' ) ? CLOUDFLARE_ACCOUNT_NAMES : array();
	$name  = isset( $names[ $account_idx ] ) ? $names[ $account_idx ] : '';
	if ( '' === $name ) {
		return 'account' . ( (int) $account_idx + 1 );
	}
	$slug = strtolower( $name );
	$slug = preg_replace( '/[^a-z0-9]+/', '-', $slug );
	$slug = trim( $slug, '-' );
	return $slug;
}

// Get all IP Lists for a Cloudflare account.
function pw_get_account_lists( $account_id, $api_key, $api_email ) {
	$headers  = array(
		"X-Auth-Email: $api_email",
		"X-Auth-Key: $api_key",
		'Content-Type: application/json',
	);
	$url      = "https://api.cloudflare.com/client/v4/accounts/{$account_id}/rules/lists";
	$response = pw_make_curl_request( $url, 'GET', $headers );

	if ( isset( $response['success'] ) && $response['success'] && isset( $response['result'] ) ) {
		return $response['result'];
	}
	return array();
}

// Check whether the fail2ban IP list exists in a Cloudflare account.
function pw_fail2ban_list_exists( $account_id, $api_key, $api_email ) {
	$list_id = defined( 'FAIL2BAN_LIST_ID' ) ? FAIL2BAN_LIST_ID : 'cf_fail2ban_blocked';
	$lists   = pw_get_account_lists( $account_id, $api_key, $api_email );
	foreach ( $lists as $list ) {
		if ( isset( $list['name'] ) && $list['name'] === $list_id ) {
			return true;
		}
	}
	return false;
}

// Return the 32-char hex UUID of the fail2ban IP list for a given account, or empty string if not found.
function pw_get_fail2ban_list_uuid( $account_id, $api_key, $api_email ) {
	$list_id = defined( 'FAIL2BAN_LIST_ID' ) ? FAIL2BAN_LIST_ID : 'cf_fail2ban_blocked';
	$lists   = pw_get_account_lists( $account_id, $api_key, $api_email );
	foreach ( $lists as $list ) {
		if ( isset( $list['name'], $list['id'] ) && $list['name'] === $list_id ) {
			return $list['id'];
		}
	}
	return '';
}

// Create the fail2ban IP list in a Cloudflare account.
function pw_create_account_list( $account_id, $api_key, $api_email ) {
	$list_id = defined( 'FAIL2BAN_LIST_ID' ) ? FAIL2BAN_LIST_ID : 'cf_fail2ban_blocked';
	$headers = array(
		"X-Auth-Email: $api_email",
		"X-Auth-Key: $api_key",
		'Content-Type: application/json',
	);
	$url     = "https://api.cloudflare.com/client/v4/accounts/{$account_id}/rules/lists";
	$data    = array(
		'name'        => $list_id,
		'description' => 'IPs banned by fail2ban \u2014 managed automatically by cloudflare-fail2ban',
		'kind'        => 'ip',
	);

	$response = pw_make_curl_request( $url, 'POST', $headers, $data );

	if ( isset( $response['success'] ) && $response['success'] ) {
		return array( 'success' => true );
	}
	$error = isset( $response['errors'][0]['message'] ) ? $response['errors'][0]['message'] : 'Unknown error';
	return array(
		'success' => false,
		'message' => $error,
	);
}

// Discover the Cloudflare permission group ID for "Account Filter Lists Write".
// This avoids hardcoding a UUID that could change across API versions.
function pw_get_fail2ban_permission_group_id( $api_key, $api_email ) {
	$headers  = array(
		"X-Auth-Email: $api_email",
		"X-Auth-Key: $api_key",
		'Content-Type: application/json',
	);
	$url      = 'https://api.cloudflare.com/client/v4/user/tokens/permission_groups';
	$response = pw_make_curl_request( $url, 'GET', $headers );

	if ( empty( $response['result'] ) ) {
		return null;
	}

	foreach ( $response['result'] as $group ) {
		$name   = strtolower( $group['name'] ?? '' );
		$scopes = $group['scopes'] ?? array();

		$is_account_scoped = in_array( 'com.cloudflare.api.account', $scopes, true );
		$is_filter_lists   = strpos( $name, 'filter' ) !== false || strpos( $name, 'lists' ) !== false;
		$is_write          = strpos( $name, 'write' ) !== false || strpos( $name, 'edit' ) !== false;

		if ( $is_account_scoped && $is_filter_lists && $is_write ) {
			return $group['id'];
		}
	}
	return null;
}

/**
 * Create a limited-scope Cloudflare API token for fail2ban (Account Filter Lists: Edit only).
 *
 * SECURITY: The token value is NEVER returned to the caller. It is written directly
 * to disk (chmod 600) and the in-memory variable is immediately unset.
 * Response contains only a boolean success and the file path — never the token value.
 *
 * @param string $account_id  Cloudflare account ID to scope the token to.
 * @param int    $account_idx Zero-based index in CLOUDFLARE_ACCOUNT_IDS (determines filename).
 * @param string $api_key     Global Cloudflare API key.
 * @param string $api_email   Cloudflare account email.
 * @return array{success: bool, message?: string, filepath?: string}
 */
function pw_create_fail2ban_token( $account_id, $account_idx, $api_key, $api_email ) {
	$token_path = defined( 'FAIL2BAN_TOKEN_PATH' ) ? FAIL2BAN_TOKEN_PATH : '';
	if ( empty( $token_path ) ) {
		return array(
			'success' => false,
			'message' => 'FAIL2BAN_TOKEN_PATH is not configured',
		);
	}

	$num        = (int) $account_idx + 1;
	$slug       = pw_get_account_slug( $account_idx );
	$token_file = rtrim( $token_path, '/' ) . '/cloudflare-api-key-' . $slug;

	$perm_group_id = pw_get_fail2ban_permission_group_id( $api_key, $api_email );
	if ( ! $perm_group_id ) {
		return array(
			'success' => false,
			'message' => 'Could not find Account Filter Lists Write permission group via API',
		);
	}

	$headers = array(
		"X-Auth-Email: $api_email",
		"X-Auth-Key: $api_key",
		'Content-Type: application/json',
	);
	$data    = array(
		'name'     => 'fail2ban-' . $slug,
		'policies' => array(
			array(
				'effect'            => 'allow',
				'resources'         => array(
					"com.cloudflare.api.account.{$account_id}" => '*',
				),
				'permission_groups' => array(
					array( 'id' => $perm_group_id ),
				),
			),
		),
	);

	$url      = 'https://api.cloudflare.com/client/v4/user/tokens';
	$response = pw_make_curl_request( $url, 'POST', $headers, $data );

	if ( ! isset( $response['success'] ) || ! $response['success'] ) {
		$error = isset( $response['errors'][0]['message'] ) ? $response['errors'][0]['message'] : 'Token creation failed';
		return array(
			'success' => false,
			'message' => $error,
		);
	}

	$token_value = $response['result']['value'] ?? null;
	if ( empty( $token_value ) ) {
		return array(
			'success' => false,
			'message' => 'Token created but value was not returned by API',
		);
	}

	// Ensure the directory exists with tight permissions before writing.
	$dir = dirname( $token_file );
	if ( ! is_dir( $dir ) ) {
		mkdir( $dir, 0700, true );
	}

	// Write token to disk, then immediately discard the value from memory.
	$bytes_written = file_put_contents( $token_file, $token_value );
	unset( $token_value ); // SECURITY: value must not linger in memory.

	if ( false === $bytes_written ) {
		return array(
			'success' => false,
			'message' => 'Token created in Cloudflare but could not write to ' . $token_file . '. Check directory permissions.',
		);
	}

	chmod( $token_file, 0600 );
	return array(
		'success'  => true,
		'filepath' => $token_file,
	);
}

/**
 * Generate the cloudflare-fail2ban-config shell file content for a given server.
 *
 * SECURITY: This output contains account IDs, server-side file paths, and list UUIDs ONLY.
 * No token values are included — they reside in separate local files.
 *
 * @param string $server_slug FAIL2BAN_SERVERS key for the target server.
 * @param string $server_name Human-readable server name for the config file header.
 * @param array  $list_uuids  Map of account_id => list UUID (32-char hex), pre-fetched by caller.
 * @return string Shell configuration file content.
 */
function pw_generate_fail2ban_config( $server_slug, $server_name, $list_uuids = array() ) {
	$all_accounts = defined( 'CLOUDFLARE_ACCOUNTS' ) ? CLOUDFLARE_ACCOUNTS : array();
	$list_id      = defined( 'FAIL2BAN_LIST_ID' ) ? FAIL2BAN_LIST_ID : 'cf_fail2ban_blocked';

	$ids_block   = '';
	$files_block = '';
	$uuids_block = '';
	$names_block = '';
	foreach ( $all_accounts as $idx => $account ) {
		// Skip accounts that list servers explicitly but don't include this one.
		if ( isset( $account['servers'] ) && ! in_array( $server_slug, $account['servers'], true ) ) {
			continue;
		}
		$slug         = pw_get_account_slug( $idx );
		$uuid         = isset( $list_uuids[ $account['id'] ] ) ? $list_uuids[ $account['id'] ] : '';
		$name         = isset( $account['name'] ) ? str_replace( array( '"', '\\', '$', '`' ), '', $account['name'] ) : $slug;
		$ids_block   .= "    \"{$account['id']}\"\n";
		$files_block .= "    \"/root/.cloudflare/cloudflare-api-key-{$slug}\"\n";
		$uuids_block .= "    \"{$uuid}\"\n";
		$names_block .= "    \"{$name}\"\n";
	}

	$safe_name = str_replace( array( '"', '\\', '$', '`' ), '', $server_name );

	$config  = "# Cloudflare Fail2ban Configuration\n";
	$config .= '# Generated by cloudflare.localhost on ' . gmdate( 'Y-m-d H:i:s' ) . " UTC\n";
	$config .= "# Server: {$safe_name}\n";
	$config .= "# Deploy to: /usr/local/bin/cloudflare-fail2ban/cloudflare-fail2ban-config\n\n";
	$config .= "CLOUDFLARE_LIST_ID=\"{$list_id}\"\n\n";
	$config .= "CLOUDFLARE_ACCOUNT_IDS=(\n{$ids_block})\n\n";
	$config .= "CLOUDFLARE_API_KEY_FILES=(\n{$files_block})\n\n";
	$config .= "# Pre-resolved list UUIDs — Cloudflare API URLs require the hex UUID, not the name.\n";
	$config .= "# Regenerate this config if you recreate a list (UUID changes).\n";
	$config .= "CLOUDFLARE_LIST_UUIDS=(\n{$uuids_block})\n\n";
	$config .= "# Human-readable account nicknames for log output (must match order of CLOUDFLARE_ACCOUNT_IDS).\n";
	$config .= "CLOUDFLARE_ACCOUNT_NICKNAMES=(\n{$names_block})\n\n";
	$config .= "SERVER_NAME=\"{$safe_name}\"\n";

	return $config;
}

// =============================================================================

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
	function pre_r( $data ) {
		print '<pre class="squarecandy-pre-r">';
		print_r( $data ); // phpcs:ignore
		print '</pre>';
	}
endif;
