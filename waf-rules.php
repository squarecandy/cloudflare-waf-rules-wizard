<?php
/**
 * WAF Rules Manager page
 */

// Direct access protection
defined( 'CLOUDFLARE_API_KEY' ) || exit( 'No direct script access allowed' );

// Split an expression string into clauses, using 'and'/'or' as line-break points.
// Each clause after the first is prefixed with its connector (and/or).
function pw_split_expr_into_clauses( $text ) {
	$text  = preg_replace( '/\s+/', ' ', trim( $text ) );
	$parts = preg_split( '/\s+(and|or)\s+/i', $text, -1, PREG_SPLIT_DELIM_CAPTURE );
	// preg_split with PREG_SPLIT_DELIM_CAPTURE returns:
	// [ clause0, connector1, clause1, connector2, clause2, ... ]
	$clauses = array();
	for ( $i = 0; $i < count( $parts ); $i++ ) {
		if ( 0 === $i ) {
			$clauses[] = trim( $parts[ $i ] );
		} elseif ( 1 === $i % 2 ) {
			// Odd index = connector, next index = clause
			$connector = strtolower( trim( $parts[ $i ] ) );
			$clause    = isset( $parts[ $i + 1 ] ) ? trim( $parts[ $i + 1 ] ) : '';
			$clauses[] = $connector . ' ' . $clause;
			$i++; // skip the clause index, already consumed
		}
	}
	return array_values( array_filter( $clauses ) );
}

// LCS-based line diff: returns array of ['type' => same|removed|added, 'line' => string]
function pw_lcs_diff( $old_arr, $new_arr ) {
	$m  = count( $old_arr );
	$n  = count( $new_arr );
	$dp = array();
	for ( $i = 0; $i <= $m; $i++ ) {
		$dp[ $i ] = array_fill( 0, $n + 1, 0 );
	}
	for ( $i = 1; $i <= $m; $i++ ) {
		for ( $j = 1; $j <= $n; $j++ ) {
			if ( $old_arr[ $i - 1 ] === $new_arr[ $j - 1 ] ) {
				$dp[ $i ][ $j ] = $dp[ $i - 1 ][ $j - 1 ] + 1;
			} else {
				$dp[ $i ][ $j ] = max( $dp[ $i - 1 ][ $j ], $dp[ $i ][ $j - 1 ] );
			}
		}
	}
	$diff = array();
	$i    = $m;
	$j    = $n;
	while ( $i > 0 || $j > 0 ) {
		if ( $i > 0 && $j > 0 && $old_arr[ $i - 1 ] === $new_arr[ $j - 1 ] ) {
			array_unshift( $diff, array( 'type' => 'same', 'line' => $old_arr[ $i - 1 ] ) );
			$i--;
			$j--;
		} elseif ( $j > 0 && ( 0 === $i || $dp[ $i ][ $j - 1 ] >= $dp[ $i - 1 ][ $j ] ) ) {
			array_unshift( $diff, array( 'type' => 'added', 'line' => $new_arr[ $j - 1 ] ) );
			$j--;
		} else {
			array_unshift( $diff, array( 'type' => 'removed', 'line' => $old_arr[ $i - 1 ] ) );
			$i--;
		}
	}
	return $diff;
}

// Convert a linear LCS diff into paired side-by-side rows.
function pw_diff_to_side_by_side( $diff_lines ) {
	$rows        = array();
	$removed_buf = array();
	$added_buf   = array();

	$flush = function () use ( &$rows, &$removed_buf, &$added_buf ) {
		$max = max( count( $removed_buf ), count( $added_buf ) );
		for ( $i = 0; $i < $max; $i++ ) {
			$rows[] = array(
				'left'       => isset( $removed_buf[ $i ] ) ? $removed_buf[ $i ] : '',
				'right'      => isset( $added_buf[ $i ] ) ? $added_buf[ $i ] : '',
				'left_type'  => isset( $removed_buf[ $i ] ) ? 'removed' : 'empty',
				'right_type' => isset( $added_buf[ $i ] ) ? 'added' : 'empty',
			);
		}
		$removed_buf = array();
		$added_buf   = array();
	};

	foreach ( $diff_lines as $entry ) {
		if ( 'same' === $entry['type'] ) {
			$flush();
			$rows[] = array(
				'left'       => $entry['line'],
				'right'      => $entry['line'],
				'left_type'  => 'same',
				'right_type' => 'same',
			);
		} elseif ( 'removed' === $entry['type'] ) {
			$removed_buf[] = $entry['line'];
		} elseif ( 'added' === $entry['type'] ) {
			$added_buf[] = $entry['line'];
		}
	}
	$flush();
	return $rows;
}

// Generate a clause-by-clause diff for a Cloudflare expression or short action string.
function pw_generate_char_diff( $old_text, $new_text ) {
	$old_clauses = pw_split_expr_into_clauses( $old_text );
	$new_clauses = pw_split_expr_into_clauses( $new_text );

	if ( empty( $old_clauses ) ) {
		$old_clauses = array( trim( $old_text ) );
	}
	if ( empty( $new_clauses ) ) {
		$new_clauses = array( trim( $new_text ) );
	}

	$diff_lines = pw_lcs_diff( $old_clauses, $new_clauses );
	return array(
		'diff_rows' => pw_diff_to_side_by_side( $diff_lines ),
	);
}

// Handle confirmation step
if ( isset( $_POST['pw_confirm_overwrite'] ) && isset( $_POST['pw_ruleset'] ) && ! empty( $_POST['pw_ruleset'] ) ) {
	$ruleset_name = $_POST['pw_ruleset'];
	$ruleset_name = preg_replace( '/[^a-z0-9_]/', '_', $ruleset_name );

	if ( isset( $rulesets[ $ruleset_name ] ) ) {
		$rules = $rulesets[ $ruleset_name ]['rules'];
		// reset the keys of the rules array (causes json errors with CF API if not done)
		$rules = array_values( $rules );
		// process the rules
		pw_cloudflare_ruleset_manager_process_zones( $rules );
	} else {
		echo '<div class="notice notice-error"><p>Invalid ruleset selected.</p></div>';
	}
}

// Show existing rules and ask for confirmation
if ( isset( $_POST['pw_create_ruleset'] ) && isset( $_POST['pw_ruleset'] ) && ! empty( $_POST['pw_ruleset'] ) && isset( $_POST['pw_zone_ids'] ) ) {
	$ruleset_name = $_POST['pw_ruleset'];
	$ruleset_name = preg_replace( '/[^a-z0-9_]/', '_', $ruleset_name );
	$zone_ids = $_POST['pw_zone_ids'];

	if ( isset( $rulesets[ $ruleset_name ] ) ) {
		$new_rules = $rulesets[ $ruleset_name ]['rules'];
		$zones = pw_get_cloudflare_zones(
			CLOUDFLARE_ACCOUNT_IDS,
			CLOUDFLARE_API_KEY,
			CLOUDFLARE_EMAIL
		);

		// Check if we need confirmation
		$needs_confirmation = false;
		$zones_data = array();

		foreach ( $zone_ids as $zone_id ) {
			$zone_name = '';
			foreach ( $zones as $zone ) {
				if ( $zone['id'] === $zone_id ) {
					$zone_name = $zone['name'];
					break;
				}
			}

			$existing_rules = pw_get_existing_waf_rules(
				$zone_id,
				CLOUDFLARE_API_KEY,
				CLOUDFLARE_EMAIL
			);

			$zones_data[] = array(
				'id' => $zone_id,
				'name' => $zone_name,
				'existing_rules' => $existing_rules,
			);

			// If zone has rules and they're not identical to new rules, we need confirmation
			if ( ! empty( $existing_rules ) ) {
				// Compare rules by their descriptions
				$existing_descriptions = array_map( function( $rule ) {
					return $rule['description'] ?? '';
				}, $existing_rules );

				$new_descriptions = array_map( function( $rule ) {
					return $rule['description'] ?? '';
				}, $new_rules );

				if ( $existing_descriptions !== $new_descriptions ) {
					$needs_confirmation = true;
				}
			}
		}

		if ( $needs_confirmation ) {
			// Show confirmation page
			?>
			<div class="confirmation-screen">
				<h2>⚠️ Confirm WAF Rules Overwrite</h2>
				<div class="notice notice-warning">
					<p><strong>Warning:</strong> You are about to overwrite existing WAF rules on the selected domains.</p>
					<p>The existing rules will be completely replaced with the new ruleset.</p>
				</div>

				<h3>New Ruleset to Apply: <?php echo htmlspecialchars( $rulesets[ $ruleset_name ]['description'] ); ?></h3>

				<?php foreach ( $zones_data as $zone_data ) : ?>
					<?php if ( ! empty( $zone_data['existing_rules'] ) ) : ?>
						<?php
						// Create a map of new rules by description for comparison
						$new_rules_map = array();
						foreach ( $new_rules as $new_rule ) {
							$desc = $new_rule['description'] ?? '';
							$new_rules_map[ $desc ] = $new_rule;
						}

						// Create a map of existing rules by description
						$existing_rules_map = array();
						foreach ( $zone_data['existing_rules'] as $existing_rule ) {
							$desc = $existing_rule['description'] ?? '';
							$existing_rules_map[ $desc ] = $existing_rule;
						}

						// Count changes
						$matching_count = 0;
						$changing_count = 0;
						$removing_count = 0;
						$adding_count = 0;

						foreach ( $zone_data['existing_rules'] as $existing_rule ) {
							$desc = $existing_rule['description'] ?? '';
							if ( isset( $new_rules_map[ $desc ] ) ) {
								// Check if rule content is identical
								$is_identical = (
									( $existing_rule['action'] ?? '' ) === ( $new_rules_map[ $desc ]['action'] ?? '' ) &&
									( $existing_rule['expression'] ?? '' ) === ( $new_rules_map[ $desc ]['expression'] ?? '' )
								);
								if ( $is_identical ) {
									$matching_count++;
								} else {
									$changing_count++;
								}
							} else {
								$removing_count++;
							}
						}

						foreach ( $new_rules as $new_rule ) {
							$desc = $new_rule['description'] ?? '';
							if ( ! isset( $existing_rules_map[ $desc ] ) ) {
								$adding_count++;
							}
						}
						?>
						<div class="zone-rules-comparison">
							<h4><?php echo htmlspecialchars( $zone_data['name'] ); ?></h4>
							<div class="rules-summary">
								<?php if ( $matching_count > 0 ) : ?>
									<span class="rule-stat rule-stat-matching">✓ <?php echo $matching_count; ?> matching</span>
								<?php endif; ?>
								<?php if ( $changing_count > 0 ) : ?>
									<span class="rule-stat rule-stat-changing">↻ <?php echo $changing_count; ?> changing</span>
								<?php endif; ?>
								<?php if ( $adding_count > 0 ) : ?>
									<span class="rule-stat rule-stat-adding">+ <?php echo $adding_count; ?> adding</span>
								<?php endif; ?>
								<?php if ( $removing_count > 0 ) : ?>
									<span class="rule-stat rule-stat-removing">− <?php echo $removing_count; ?> removing</span>
								<?php endif; ?>
							</div>

							<table class="rules-comparison-table">
								<thead>
									<tr>
										<th>Status</th>
										<th>Rule Description</th>
										<th>Current Action</th>
										<th>New Action</th>
									</tr>
								</thead>
								<tbody>
									<?php
									// Show existing rules first
									foreach ( $zone_data['existing_rules'] as $existing_rule ) :
										$desc = $existing_rule['description'] ?? 'Unnamed Rule';
										$existing_action = $existing_rule['action'] ?? 'N/A';
										$existing_expr = $existing_rule['expression'] ?? '';

										if ( isset( $new_rules_map[ $desc ] ) ) :
											$new_action = $new_rules_map[ $desc ]['action'] ?? 'N/A';
											$new_expr = $new_rules_map[ $desc ]['expression'] ?? '';
											$is_identical = ( $existing_action === $new_action && $existing_expr === $new_expr );
											$status_class = $is_identical ? 'matching' : 'changing';
											$status_icon = $is_identical ? '✓' : '↻';
											$rule_row_id = 'rule-' . md5( $desc );
											?>
											<tr class="rule-<?php echo $status_class; ?>">
												<td class="rule-status">
													<span class="status-badge status-<?php echo $status_class; ?>">
														<?php echo $status_icon; ?> <?php echo $is_identical ? 'Match' : 'Change'; ?>
													</span>
												</td>
												<td class="rule-description">
													<strong><?php echo htmlspecialchars( $desc ); ?></strong>
													<?php if ( ! $is_identical ) : ?>
														<button type="button" class="diff-toggle" onclick="toggleDiff('<?php echo $rule_row_id; ?>')">
															<span class="toggle-icon">▶</span><span class="toggle-text"> Show Diff</span>
														</button>
													<?php endif; ?>
												</td>
												<td class="rule-action"><?php echo htmlspecialchars( $existing_action ); ?></td>
												<td class="rule-action"><?php echo htmlspecialchars( $new_action ); ?></td>
											</tr>
											<?php if ( ! $is_identical ) : ?>
												<tr id="<?php echo $rule_row_id; ?>" class="diff-row" style="display: none;">
													<td colspan="4">
														<div class="diff-container">
															<?php if ( $existing_expr !== $new_expr ) : ?>
																<div class="diff-section">
																	<h5>Expression Changes:</h5>
																<?php
																$expr_diff = pw_generate_char_diff( $existing_expr, $new_expr );
																?>
																	<table class="diff-side-by-side">
																		<thead><tr><th>Before</th><th>After</th></tr></thead>
																		<tbody>
																		<?php foreach ( $expr_diff['diff_rows'] as $row ) : ?>
																			<tr>
																				<td class="diff-cell diff-cell-<?php echo $row['left_type']; ?>"><?php echo htmlspecialchars( $row['left'], ENT_QUOTES, 'UTF-8' ); ?></td>
																				<td class="diff-cell diff-cell-<?php echo $row['right_type']; ?>"><?php echo htmlspecialchars( $row['right'], ENT_QUOTES, 'UTF-8' ); ?></td>
																			</tr>
																		<?php endforeach; ?>
																		</tbody>
																	</table>
																</div>
															<?php endif; ?>
														<?php if ( $existing_action !== $new_action ) : ?>
															<div class="diff-section">
																<h5>Action Changes:</h5>
																<?php
																$action_diff = pw_generate_char_diff( $existing_action, $new_action );
																?>
																<table class="diff-side-by-side">
																	<thead><tr><th>Before</th><th>After</th></tr></thead>
																	<tbody>
																	<?php foreach ( $action_diff['diff_rows'] as $row ) : ?>
																		<tr>
																			<td class="diff-cell diff-cell-<?php echo $row['left_type']; ?>"><?php echo htmlspecialchars( $row['left'], ENT_QUOTES, 'UTF-8' ); ?></td>
																			<td class="diff-cell diff-cell-<?php echo $row['right_type']; ?>"><?php echo htmlspecialchars( $row['right'], ENT_QUOTES, 'UTF-8' ); ?></td>
																		</tr>
																	<?php endforeach; ?>
																	</tbody>
																</table>
															</div>
														<?php endif; ?>
														</div>
													</td>
												</tr>
											<?php endif; ?>
										<?php else : ?>
											<tr class="rule-removing">
												<td class="rule-status">
													<span class="status-badge status-removing">− Remove</span>
												</td>
												<td class="rule-description"><strong><?php echo htmlspecialchars( $desc ); ?></strong></td>
												<td class="rule-action"><?php echo htmlspecialchars( $existing_action ); ?></td>
												<td class="rule-action"><em>—</em></td>
											</tr>
										<?php endif; ?>
									<?php endforeach; ?>

									<?php
									// Show new rules that don't exist yet
									foreach ( $new_rules as $new_rule ) :
										$desc = $new_rule['description'] ?? 'Unnamed Rule';
										if ( ! isset( $existing_rules_map[ $desc ] ) ) :
											$new_action = $new_rule['action'] ?? 'N/A';
											?>
											<tr class="rule-adding">
												<td class="rule-status">
													<span class="status-badge status-adding">+ Add</span>
												</td>
												<td class="rule-description"><strong><?php echo htmlspecialchars( $desc ); ?></strong></td>
												<td class="rule-action"><em>—</em></td>
												<td class="rule-action"><?php echo htmlspecialchars( $new_action ); ?></td>
											</tr>
										<?php endif; ?>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					<?php endif; ?>
				<?php endforeach; ?>

				<div class="confirmation-actions">
					<form method="post" style="display: inline;">
						<input type="hidden" name="pw_ruleset" value="<?php echo htmlspecialchars( $_POST['pw_ruleset'] ); ?>">
						<?php foreach ( $zone_ids as $zone_id ) : ?>
							<input type="hidden" name="pw_zone_ids[]" value="<?php echo htmlspecialchars( $zone_id ); ?>">
						<?php endforeach; ?>
						<input type="submit" class="button button-primary" name="pw_confirm_overwrite" value="✓ Yes, Overwrite Rules">
						<a href="index.php?page=waf-rules" class="button button-secondary">✗ Cancel</a>
					</form>
				</div>
			</div>
			<script>
			function toggleDiff(ruleId) {
				const diffRow = document.getElementById(ruleId);
				const toggleBtn = event.target.closest('.diff-toggle');
				const icon = toggleBtn.querySelector('.toggle-icon');
				const text = toggleBtn.querySelector('.toggle-text');

				if (diffRow.style.display === 'none') {
					diffRow.style.display = 'table-row';
					icon.textContent = '▼';
					text.textContent = ' Hide Diff';
				} else {
					diffRow.style.display = 'none';
					icon.textContent = '▶';
					text.textContent = ' Show Diff';
				}
			}
			</script>
			<?php
			return; // Stop processing to show confirmation
		} else {
			// No confirmation needed - process directly
			$rules = array_values( $new_rules );
			pw_cloudflare_ruleset_manager_process_zones( $rules );
		}
	} else {
		echo '<div class="notice notice-error"><p>Invalid ruleset selected.</p></div>';
	}
}

if ( ( isset( $_POST['pw_test_ruleset'] ) ) && isset( $_POST['pw_ruleset'] ) && ! empty( $_POST['pw_ruleset'] ) ) {
	$ruleset_name = $_POST['pw_ruleset'];
	$ruleset_name = preg_replace( '/[^a-z0-9_]/', '_', $ruleset_name );

	if ( isset( $rulesets[ $ruleset_name ] ) ) {
		$rules = $rulesets[ $ruleset_name ]['rules'];
		foreach ( $rules as $rule ) {
			echo '<h2>' . $rule['description'] . '<br>' . $rule['action'] . '</h2>';
			echo '<textarea>' . $rule['expression'] . '</textarea>';
		}
		echo '<br><br><hr><br>';
	} else {
		echo '<div class="notice notice-error"><p>Invalid ruleset selected.</p></div>';
	}
}
?>

<h2>WAF Rules Manager</h2>

<form method="post">
	<?php
	$zones = pw_get_cloudflare_zones(
		CLOUDFLARE_ACCOUNT_IDS,
		CLOUDFLARE_API_KEY,
		CLOUDFLARE_EMAIL
	);
	?>
	<h3>Select Ruleset to Apply:</h3>
	<select name="pw_ruleset">
		<?php foreach ( $rulesets as $ruleset_key => $ruleset ) : ?>
			<?php $selected = isset( $_POST['pw_ruleset'] ) && $_POST['pw_ruleset'] === $ruleset_key ? ' selected' : ''; ?>
			<option value="<?php echo $ruleset_key; ?>"<?php echo $selected; ?>>
				<?php echo $ruleset['description']; ?>
			</option>
		<?php endforeach; ?>
	</select>
	<br/>
	<input type="submit" class="button button-secondary" name="pw_test_ruleset" value="Test Ruleset">
	<h3>Select Domains to Reset WAF Custom Rules on:</h3>
	<?php foreach ( $zones as $zone ) : ?>
		<label>
			<input type="checkbox" name="pw_zone_ids[]" value="<?php echo $zone['id']; ?>">
			<?php echo $zone['name']; ?>
		</label><br>
	<?php endforeach; ?>
	<br/>
	<input type="submit" class="button button-primary" name="pw_create_ruleset" value="Create/Overwrite All WAF Rules"><br><br>
</form>
