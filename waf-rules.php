<?php
/**
 * WAF Rules Manager page
 */

// Direct access protection
defined( 'CLOUDFLARE_API_KEY' ) || exit( 'No direct script access allowed' );

/**
 * Return the ruleset key assigned to a zone by ZONE_RULESET_MAP config.
 * Falls back to 'squarecandy_rules' if not configured or key is unknown.
 *
 * @global array $rulesets
 * @param string $zone_name Domain name.
 * @return string Ruleset key.
 */
function pw_get_zone_ruleset_key( $zone_name ) {
	global $rulesets;
	$map = defined( 'ZONE_RULESET_MAP' ) ? ZONE_RULESET_MAP : array();
	$key = isset( $map[ $zone_name ] ) ? $map[ $zone_name ] : 'squarecandy_rules';
	return isset( $rulesets[ $key ] ) ? $key : 'squarecandy_rules';
}

// Show confirmation skeleton — diffs and apply are handled via AJAX.
if ( isset( $_POST['pw_create_ruleset'] ) && isset( $_POST['pw_ruleset'] ) && ! empty( $_POST['pw_ruleset'] ) && isset( $_POST['pw_zone_ids'] ) ) {
	$ruleset_name = preg_replace( '/[^a-z0-9_]/', '_', $_POST['pw_ruleset'] ); // phpcs:ignore WordPress.Security.NonceVerification
	$zone_ids     = array_map( // phpcs:ignore WordPress.Security.NonceVerification
		function ( $id ) {
			return preg_replace( '/[^a-zA-Z0-9]/', '', $id );
		},
		(array) $_POST['pw_zone_ids'] // phpcs:ignore WordPress.Security.NonceVerification
	);
	$zone_ids     = array_values( array_filter( $zone_ids ) );

	if ( ! isset( $rulesets[ $ruleset_name ] ) ) {
		echo '<div class="notice notice-error"><p>Invalid ruleset selected.</p></div>';
	} elseif ( empty( $zone_ids ) ) {
		echo '<div class="notice notice-error"><p>No zones selected.</p></div>';
	} else {
		$ruleset_desc  = $rulesets[ $ruleset_name ]['description'];
		$zones_cache   = pw_cache_get( 'zones' );
		$zones_cache   = $zones_cache ? $zones_cache : array();
		$zone_name_map = array();
		foreach ( $zones_cache as $z ) {
			$zone_name_map[ $z['id'] ] = $z['name'];
		}
		$zone_count = count( $zone_ids );
		?>
		<div class="confirmation-screen">
			<h2>⚠️ Confirm WAF Rules Apply</h2>
			<div class="notice notice-warning">
				<p>Applying <strong><?php echo htmlspecialchars( $ruleset_desc, ENT_QUOTES, 'UTF-8' ); ?></strong>
				to <strong><?php echo $zone_count; ?> zone<?php echo $zone_count !== 1 ? 's' : ''; ?></strong>.
				Diffs are loading below — review before applying.</p>
			</div>
			<p id="diff-load-status">Loading diffs: <span id="diff-progress">0</span> / <?php echo $zone_count; ?></p>

			<div class="zone-diff-blocks">
				<?php foreach ( $zone_ids as $zone_id ) : ?>
					<?php $zone_name = isset( $zone_name_map[ $zone_id ] ) ? $zone_name_map[ $zone_id ] : $zone_id; ?>
					<div class="zone-diff-block"
						data-zone-id="<?php echo htmlspecialchars( $zone_id, ENT_QUOTES, 'UTF-8' ); ?>"
						data-zone-name="<?php echo htmlspecialchars( $zone_name, ENT_QUOTES, 'UTF-8' ); ?>">
						<h4 class="zone-diff-heading"><?php echo htmlspecialchars( $zone_name, ENT_QUOTES, 'UTF-8' ); ?></h4>
						<div class="zone-diff-content"><span class="zone-diff-spinner">⟳ Loading diff…</span></div>
						<div class="zone-apply-result"></div>
					</div>
				<?php endforeach; ?>
			</div>

			<div class="confirmation-actions">
				<button id="apply-all-btn" class="button button-primary" disabled
					data-ruleset="<?php echo htmlspecialchars( $ruleset_name, ENT_QUOTES, 'UTF-8' ); ?>">
					✓ Apply Rules (<span id="zones-ready-count">0</span> / <?php echo $zone_count; ?> ready)
				</button>
				<a href="index.php?page=waf-rules" class="button button-secondary">✗ Cancel</a>
				<span id="apply-progress-msg"></span>
			</div>
		</div>

		<script>
		(function () {
			const blocks = Array.from(document.querySelectorAll('.zone-diff-block'));
			const applyBtn = document.getElementById('apply-all-btn');
			const progressEl = document.getElementById('diff-progress');
			const readyCountEl = document.getElementById('zones-ready-count');
			const loadStatusEl = document.getElementById('diff-load-status');
			const progressMsg = document.getElementById('apply-progress-msg');
			const ruleset = applyBtn.dataset.ruleset;
			let loadedCount = 0;

			function toggleDiff(btn, id) {
				const row = document.getElementById(id);
				const icon = btn.querySelector('.toggle-icon');
				const text = btn.querySelector('.toggle-text');
				if (row.style.display === 'none') {
					row.style.display = 'table-row';
					icon.textContent = '▼';
					text.textContent = ' Hide Diff';
				} else {
					row.style.display = 'none';
					icon.textContent = '▶';
					text.textContent = ' Show Diff';
				}
			}
			window.toggleDiff = toggleDiff;

			function markLoaded() {
				loadedCount++;
				progressEl.textContent = loadedCount;
				readyCountEl.textContent = loadedCount;
				if (loadedCount === blocks.length) {
					loadStatusEl.style.display = 'none';
					applyBtn.disabled = false;
				}
			}

			(async function loadDiffs() {
				for (const block of blocks) {
					const zoneId = block.dataset.zoneId;
					const contentEl = block.querySelector('.zone-diff-content');
					try {
						const fd = new FormData();
						fd.append('setting', 'get_zone_diff');
						fd.append('zone_id', zoneId);
						fd.append('ruleset', ruleset);
						const res = await fetch('ajax-handler.php', {
							method: 'POST', body: fd,
							headers: {'X-Requested-With': 'XMLHttpRequest'}
						});
						const data = await res.json();
						contentEl.innerHTML = data.success
							? data.html
							: '<p class="notice notice-error">' + (data.message || 'Failed to load diff.') + '</p>';
					} catch (e) {
						contentEl.innerHTML = '<p class="notice notice-error">Network error loading diff.</p>';
					}
					markLoaded();
				}
			}());

			applyBtn.addEventListener('click', async function () {
				applyBtn.disabled = true;
				progressMsg.textContent = '';
				let applied = 0, failed = 0;

				for (const block of blocks) {
					const zoneId = block.dataset.zoneId;
					const resultEl = block.querySelector('.zone-apply-result');
					resultEl.innerHTML = '<span class="zone-applying">↻ Applying…</span>';
					block.scrollIntoView({behavior: 'smooth', block: 'nearest'});
					try {
						const fd = new FormData();
						fd.append('setting', 'apply_zone_ruleset');
						fd.append('zone_id', zoneId);
						fd.append('ruleset', ruleset);
						const res = await fetch('ajax-handler.php', {
							method: 'POST', body: fd,
							headers: {'X-Requested-With': 'XMLHttpRequest'}
						});
						const data = await res.json();
						if (data.success) {
							resultEl.innerHTML = '<div class="notice notice-success"><p>✓ Updated successfully.</p></div>';
							applied++;
						} else {
							resultEl.innerHTML = '<div class="notice notice-error"><p>✗ ' + (data.message || 'Failed.') + '</p></div>';
							failed++;
						}
					} catch (e) {
						resultEl.innerHTML = '<div class="notice notice-error"><p>✗ Network error.</p></div>';
						failed++;
					}
				}

				if (failed === 0) {
					progressMsg.innerHTML = '<strong style="color:#175319">✓ All ' + applied + ' zones updated.</strong>';
				} else {
					progressMsg.innerHTML = '<strong style="color:#a00">⚠ ' + applied + ' updated, ' + failed + ' failed — check above.</strong>';
					applyBtn.disabled = false;
					applyBtn.textContent = '↻ Retry Failed Zones';
				}
			});
		}());
		</script>
		<?php
		return;
	}
}

?>

<h2>WAF Rules Manager</h2>

<?php
$zones     = pw_cache_get( 'zones' );
$cache_age = pw_cache_age_label( 'zones' );
?>

<div class="cache-controls">
	<?php if ( $cache_age ) : ?>
		<span class="cache-age">Zones last refreshed: <?php echo esc_html( $cache_age ); ?></span>
	<?php else : ?>
		<span class="cache-age cache-age-empty">No zones cached yet.</span>
	<?php endif; ?>
	<button id="refresh-waf-btn" class="button-secondary btn-refresh">
		<span class="btn-icon">↻</span> Refresh Zones
	</button>
	<div id="refresh-message" class="notice hidden"><p id="refresh-message-text"></p></div>
</div>

<?php if ( ! $zones ) : ?>
	<div class="notice notice-info">
		<p>No cached zone list available. Click <strong>Refresh Zones</strong> to load the domain list from Cloudflare.</p>
	</div>
<?php else : ?>

	<?php
	// Build groups: zone objects keyed by ruleset key.
	$waf_groups = array();
	foreach ( $zones as $zone ) {
		$rkey                  = pw_get_zone_ruleset_key( $zone['name'] );
		$waf_groups[ $rkey ]   = isset( $waf_groups[ $rkey ] ) ? $waf_groups[ $rkey ] : array();
		$waf_groups[ $rkey ][] = $zone;
	}
	?>

	<div class="waf-grouped-sections">
		<?php foreach ( $waf_groups as $group_key => $group_zones ) : ?>
			<?php $group_desc = isset( $rulesets[ $group_key ] ) ? $rulesets[ $group_key ]['description'] : $group_key; ?>
			<div class="waf-group">
				<h3 class="waf-group-title">
					<?php echo htmlspecialchars( $group_desc, ENT_QUOTES, 'UTF-8' ); ?>
					<code class="ruleset-key"><?php echo htmlspecialchars( $group_key, ENT_QUOTES, 'UTF-8' ); ?></code>
					<span class="zone-count"><?php echo count( $group_zones ); ?> site<?php echo count( $group_zones ) !== 1 ? 's' : ''; ?></span>
				</h3>
				<form method="post" class="waf-group-form">
					<input type="hidden" name="pw_ruleset" value="<?php echo htmlspecialchars( $group_key, ENT_QUOTES, 'UTF-8' ); ?>">
					<div class="waf-group-zones">
						<?php foreach ( $group_zones as $zone ) : ?>
							<label class="zone-checkbox-label">
								<input type="checkbox" name="pw_zone_ids[]" value="<?php echo htmlspecialchars( $zone['id'], ENT_QUOTES, 'UTF-8' ); ?>" checked>
								<?php echo htmlspecialchars( $zone['name'], ENT_QUOTES, 'UTF-8' ); ?>
							</label>
						<?php endforeach; ?>
					</div>
					<div class="waf-group-actions">
						<input type="submit" class="button button-primary" name="pw_create_ruleset"
							value="Apply <?php echo htmlspecialchars( $group_desc, ENT_QUOTES, 'UTF-8' ); ?> to selected">
					</div>
				</form>
			</div>
		<?php endforeach; ?>
	</div>


	<div class="manual-override">
		<h3>Manual Override — apply any ruleset to any zones</h3>
		<form method="post">
			<h4>Select Ruleset to Apply:</h4>
			<select name="pw_ruleset">
				<?php foreach ( $rulesets as $ruleset_key => $ruleset ) : ?>
					<?php $selected = isset( $_POST['pw_ruleset'] ) && $_POST['pw_ruleset'] === $ruleset_key ? ' selected' : ''; ?>
					<option value="<?php echo htmlspecialchars( $ruleset_key, ENT_QUOTES, 'UTF-8' ); ?>"<?php echo $selected; ?>>
						<?php echo htmlspecialchars( $ruleset['description'], ENT_QUOTES, 'UTF-8' ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<h3>Select Domains:</h3>
			<h4>Select Domains:</h4>
			<div class="waf-group-zones">
				<?php foreach ( $zones as $zone ) : ?>
					<label class="zone-checkbox-label">
						<input type="checkbox" name="pw_zone_ids[]" value="<?php echo htmlspecialchars( $zone['id'], ENT_QUOTES, 'UTF-8' ); ?>">
						<?php echo htmlspecialchars( $zone['name'], ENT_QUOTES, 'UTF-8' ); ?>
					</label>
				<?php endforeach; ?>
			</div>
			<div class="waf-group-actions">
				<input type="submit" class="button button-primary" name="pw_create_ruleset" value="Apply Selected Ruleset">
			</div>
		</form>
	</div>

<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
	const refreshBtn = document.getElementById('refresh-waf-btn');
	const msgBox = document.getElementById('refresh-message');
	const msgText = document.getElementById('refresh-message-text');
	if (refreshBtn) {
		refreshBtn.addEventListener('click', function() {
			this.disabled = true;
			this.textContent = '↻ Loading…';
			const formData = new FormData();
			formData.append('setting', 'refresh_zones');
			fetch('ajax-handler.php', {
				method: 'POST',
				body: formData,
				headers: { 'X-Requested-With': 'XMLHttpRequest' }
			})
			.then(response => response.json())
			.then(data => {
				if (data.success) {
					location.reload();
				} else {
					msgBox.className = 'notice notice-error';
					msgText.textContent = data.message || 'Refresh failed.';
					this.disabled = false;
					this.innerHTML = '<span class="btn-icon">↻</span> Refresh Zones';
				}
			})
			.catch(() => {
				msgBox.className = 'notice notice-error';
				msgText.textContent = 'Network error during refresh.';
				this.disabled = false;
				this.innerHTML = '<span class="btn-icon">↻</span> Refresh Zones';
			});
		});
	}
});
</script>
