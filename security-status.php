<?php
/**
 * Security Features Status page
 */

// Direct access protection
defined( 'CLOUDFLARE_API_KEY' ) || exit( 'No direct script access allowed' );
?>

<h2>Cloudflare Security Features Status</h2>

<div id="ajax-message" class="notice hidden">
	<p id="ajax-message-text"></p>
</div>

<table class="security-table">
	<thead>
		<tr>
			<th>Domain</th>
			<th>Bot Fight Mode</th>
			<th>Block AI Bots</th>
			<th>AI Labyrinth</th>
			<th>JS Detection</th>
			<th>CF robots.txt</th>
		</tr>
	</thead>
	<tbody>
		<?php
		$zones = pw_get_cloudflare_zones(
			CLOUDFLARE_ACCOUNT_IDS,
			CLOUDFLARE_API_KEY,
			CLOUDFLARE_EMAIL
		);
		foreach ( $zones as $zone ) :
			$zone_id  = $zone['id'];
			$settings = pw_get_zone_security_settings(
				$zone_id,
				CLOUDFLARE_API_KEY,
				CLOUDFLARE_EMAIL
			);
			?>
		<tr data-zone-id="<?php echo $zone_id; ?>">
			<td class="zone_domain"><?php echo $zone['name']; ?></td>
			<td class="setting-cell <?php echo strtolower( $settings['bot_fight_mode'] ); ?>" 
				data-setting="bot_fight_mode"
				data-value="<?php echo $settings['bot_fight_mode'] === 'On' ? 'true' : 'false'; ?>">
				<?php echo $settings['bot_fight_mode']; ?>
			</td>
			<td class="setting-cell <?php echo strtolower( $settings['block_ai_bots'] ); ?>" 
				data-setting="block_ai_bots" 
				data-value="<?php echo $settings['block_ai_bots'] === 'On' ? 'true' : 'false'; ?>">
				<?php echo $settings['block_ai_bots']; ?>
			</td>
			<td class="setting-cell <?php echo strtolower( $settings['ai_labyrinth'] ); ?>" 
				data-setting="ai_labyrinth"
				data-value="<?php echo $settings['ai_labyrinth'] === 'On' ? 'true' : 'false'; ?>">
				<?php echo $settings['ai_labyrinth']; ?>
			</td>
			<td class="setting-cell <?php echo strtolower( $settings['javascript_detection'] ); ?>" 
				data-setting="javascript_detection"
				data-value="<?php echo $settings['javascript_detection'] === 'On' ? 'true' : 'false'; ?>">
				<?php echo $settings['javascript_detection']; ?>
			</td>
			<td class="setting-cell <?php echo strtolower( $settings['robots_management'] ); ?>" 
				data-setting="robots_management"
				data-value="<?php echo $settings['robots_management'] === 'On' ? 'true' : 'false'; ?>">
				<?php echo $settings['robots_management']; ?>
			</td>
		</tr>
		<?php endforeach; ?>
	</tbody>
</table>

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
	const cells = document.querySelectorAll('.setting-cell');
	const messageBox = document.getElementById('ajax-message');
	const messageText = document.getElementById('ajax-message-text');
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
