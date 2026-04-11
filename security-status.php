<?php
/**
 * Security Features Status page
 */

// Direct access protection
defined( 'CLOUDFLARE_API_KEY' ) || exit( 'No direct script access allowed' );

$cached_zones    = pw_cache_get( 'zones' );
$cached_security = pw_cache_get( 'security_status' );
$cache_age       = pw_cache_age_label( 'security_status' );
?>

<h2>Cloudflare Security Features Status</h2>

<div class="cache-controls">
	<?php if ( $cache_age ) : ?>
		<span class="cache-age">Last refreshed: <?php echo esc_html( $cache_age ); ?></span>
	<?php else : ?>
		<span class="cache-age cache-age-empty">No data cached yet.</span>
	<?php endif; ?>
	<button id="refresh-security-btn" class="button-secondary btn-refresh">
		<span class="btn-icon">↻</span> Refresh Data
	</button>
</div>

<div id="ajax-message" class="notice hidden">
	<p id="ajax-message-text"></p>
</div>

<?php if ( ! $cached_zones || ! $cached_security ) : ?>
	<div class="notice notice-info">
		<p>No cached data available. Click <strong>Refresh Data</strong> to load security settings from the Cloudflare API.</p>
	</div>
<?php else : ?>
<table class="security-table">
	<thead>
		<tr>
			<th>Domain</th>
			<th>Zone ID</th>
			<th>Bot Fight Mode</th>
			<th>Block AI Bots</th>
			<th>AI Labyrinth</th>
			<th>JS Detection</th>
			<th>CF robots.txt</th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ( $cached_zones as $zone ) : ?>
			<?php
			$zone_id  = $zone['id'];
			$settings = isset( $cached_security[ $zone_id ] ) ? $cached_security[ $zone_id ] : array(
				'bot_fight_mode'       => 'Unknown',
				'block_ai_bots'        => 'Unknown',
				'ai_labyrinth'         => 'Unknown',
				'robots_management'    => 'Unknown',
				'javascript_detection' => 'Unknown',
			);
			?>
			<tr data-zone-id="<?php echo htmlspecialchars( $zone_id, ENT_QUOTES, 'UTF-8' ); ?>">
				<td class="zone_domain"><?php echo htmlspecialchars( $zone['name'], ENT_QUOTES, 'UTF-8' ); ?></td>
				<td class="zone_id">
					<code><?php echo htmlspecialchars( $zone_id, ENT_QUOTES, 'UTF-8' ); ?></code>
				</td>
				<td class="setting-cell <?php echo htmlspecialchars( strtolower( $settings['bot_fight_mode'] ), ENT_QUOTES, 'UTF-8' ); ?>"
					data-setting="bot_fight_mode"
					data-value="<?php echo 'On' === $settings['bot_fight_mode'] ? 'true' : 'false'; ?>">
					<?php echo htmlspecialchars( $settings['bot_fight_mode'], ENT_QUOTES, 'UTF-8' ); ?>
				</td>
				<td class="setting-cell <?php echo htmlspecialchars( strtolower( $settings['block_ai_bots'] ), ENT_QUOTES, 'UTF-8' ); ?>"
					data-setting="block_ai_bots"
					data-value="<?php echo 'On' === $settings['block_ai_bots'] ? 'true' : 'false'; ?>">
					<?php echo htmlspecialchars( $settings['block_ai_bots'], ENT_QUOTES, 'UTF-8' ); ?>
				</td>
				<td class="setting-cell <?php echo htmlspecialchars( strtolower( $settings['ai_labyrinth'] ), ENT_QUOTES, 'UTF-8' ); ?>"
					data-setting="ai_labyrinth"
					data-value="<?php echo 'On' === $settings['ai_labyrinth'] ? 'true' : 'false'; ?>">
					<?php echo htmlspecialchars( $settings['ai_labyrinth'], ENT_QUOTES, 'UTF-8' ); ?>
				</td>
				<td class="setting-cell <?php echo htmlspecialchars( strtolower( $settings['javascript_detection'] ), ENT_QUOTES, 'UTF-8' ); ?>"
					data-setting="javascript_detection"
					data-value="<?php echo 'On' === $settings['javascript_detection'] ? 'true' : 'false'; ?>">
					<?php echo htmlspecialchars( $settings['javascript_detection'], ENT_QUOTES, 'UTF-8' ); ?>
				</td>
				<td class="setting-cell <?php echo htmlspecialchars( strtolower( $settings['robots_management'] ), ENT_QUOTES, 'UTF-8' ); ?>"
					data-setting="robots_management"
					data-value="<?php echo 'On' === $settings['robots_management'] ? 'true' : 'false'; ?>">
					<?php echo htmlspecialchars( $settings['robots_management'], ENT_QUOTES, 'UTF-8' ); ?>
				</td>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>
<?php endif; ?>

<div class="help">
	<h2>Bot fight mode</h2>
	<p>Detect and challenges bot traffic on your domain.
	<br>
	<a target="_blank" href="https://developers.cloudflare.com/bots/get-started/bot-fight-mode/">Developer Docs</a></p>
	<h2>Block AI Bots</h2>
	<p>Block artificial intelligence (AI) bots from scraping your websites and training large language models (LLM) on your content without your permission.
	<br>
	<a target="_blank" href="https://developers.cloudflare.com/bots/get-started/bot-management/#block-ai-bots">Developer Docs</a></p>
	<h2>Manage bot traffic with robots.txt</h2>
	<p>Use a Cloudflare managed robots.txt file, to allow verified AI bots for non-scraping purposes.<br>
	<strong>Note: Current robots.txt will be bypassed.</strong></p>
	<h2>AI Labyrinth</h2>
	<p>Cloudflare modifies your pages by adding nofollow links that contain AI-generated content to disrupt bots ignoring crawling standards.</p>
	<h2>Javascript Detections</h2>
	<p>Use lightweight, invisible JavaScript code snippets that follow Cloudflare's privacy standards to improve Bot Management.</p>
</div>



<script>
document.addEventListener('DOMContentLoaded', function() {
	const messageBox = document.getElementById('ajax-message');
	const messageText = document.getElementById('ajax-message-text');

	// Refresh button
	const refreshBtn = document.getElementById('refresh-security-btn');
	if (refreshBtn) {
		refreshBtn.addEventListener('click', function() {
			this.disabled = true;
			this.textContent = '↻ Loading…';
			const formData = new FormData();
			formData.append('setting', 'refresh_security_status');
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
					messageBox.className = 'notice notice-error';
					messageText.textContent = data.message || 'Refresh failed.';
					this.disabled = false;
					this.innerHTML = '<span class="btn-icon">↻</span> Refresh Data';
				}
			})
			.catch(() => {
				messageBox.className = 'notice notice-error';
				messageText.textContent = 'Network error during refresh.';
				this.disabled = false;
				this.innerHTML = '<span class="btn-icon">↻</span> Refresh Data';
			});
		});
	}

	const cells = document.querySelectorAll('.setting-cell');
	// Add click event to all setting cells
	cells.forEach(cell => {
		cell.addEventListener('click', function() {
			const settingName = this.dataset.setting;
			const currentValue = this.dataset.value;
			const newValue = currentValue === 'true' ? 'false' : 'true';
			const zoneId = this.parentNode.dataset.zoneId;

			// Show loading state
			this.classList.add('loading');
			this.textContent = 'Updating...';
			// Prepare form data
			const formData = new FormData();
			formData.append('zone_id', zoneId);
			formData.append('setting', settingName);
			formData.append('value', newValue);
			// Send AJAX request
			fetch('ajax-handler.php', {
				method: 'POST',
				body: formData,
				headers: {
					'X-Requested-With': 'XMLHttpRequest'
				}
			})
			.then(response => response.json())
			.then(data => {
				// Update cell
				this.classList.remove('loading');
				if (data.success) {
					// Update cell value and appearance
					this.textContent = data.new_value;
					this.dataset.value = newValue;
					this.className = 'setting-cell ' + data.new_value.toLowerCase();
					// Show success message
					messageBox.className = 'notice notice-success';
					messageText.textContent = data.message;
				} else {
					// Show error and restore original state
					this.textContent = currentValue === 'true' ? 'On' : 'Off';
					messageBox.className = 'notice notice-error';
					messageText.textContent = data.message;
				}
				// Hide message after some time
				setTimeout(() => {
					messageBox.className = 'notice hidden';
				}, 7 * 1000);
			})
			.catch(error => {
				console.error('Error:', error);
				this.classList.remove('loading');
				this.textContent = currentValue === 'true' ? 'On' : 'Off';
				messageBox.className = 'notice notice-error';
				messageText.textContent = 'Network error occurred';
				setTimeout(() => {
					messageBox.className = 'notice hidden';
				}, 7 * 1000);
			});
		});
	});
});
</script>
