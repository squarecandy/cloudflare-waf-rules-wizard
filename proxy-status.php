<?php
/**
 * DNS Proxy Status page
 */

// Direct access protection
defined( 'CLOUDFLARE_API_KEY' ) || exit( 'No direct script access allowed' );
?>

<h2>Cloudflare DNS Proxy Status</h2>

<div id="ajax-message" class="notice hidden">
	<p id="ajax-message-text"></p>
</div>

<?php
$zones = pw_get_cloudflare_zones(
	CLOUDFLARE_ACCOUNT_IDS,
	CLOUDFLARE_API_KEY,
	CLOUDFLARE_EMAIL
);

foreach ( $zones as $zone ) :
	$zone_id      = $zone['id'];
	$dns_records  = pw_get_zone_dns_records(
		$zone_id,
		CLOUDFLARE_API_KEY,
		CLOUDFLARE_EMAIL
	);
	?>

	<h3><?php echo htmlspecialchars( $zone['name'] ); ?></h3>
	<p class="zone-id-display">Zone ID: <code><?php echo htmlspecialchars( $zone_id ); ?></code></p>

	<?php if ( empty( $dns_records ) ) : ?>
		<p class="notice notice-warning">No proxiable DNS records found for this zone.</p>
	<?php else : ?>
		<table class="dns-records-table">
			<thead>
				<tr>
					<th>Type</th>
					<th>Name</th>
					<th>Content</th>
					<th>Proxy Status</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $dns_records as $record ) : ?>
					<tr data-zone-id="<?php echo htmlspecialchars( $zone_id ); ?>" data-record-id="<?php echo htmlspecialchars( $record['id'] ); ?>">
						<td class="record-type">
							<span class="type-badge type-<?php echo strtolower( $record['type'] ); ?>">
								<?php echo htmlspecialchars( $record['type'] ); ?>
							</span>
						</td>
						<td class="record-name"><?php echo htmlspecialchars( $record['name'] ); ?></td>
						<td class="record-content"><?php echo htmlspecialchars( $record['content'] ); ?></td>
						<td class="proxy-cell">
							<button class="proxy-toggle-btn <?php echo $record['proxied'] ? 'proxied' : 'not-proxied'; ?>"
								data-record-id="<?php echo htmlspecialchars( $record['id'] ); ?>"
								data-zone-id="<?php echo htmlspecialchars( $zone_id ); ?>"
								data-proxied="<?php echo $record['proxied'] ? 'true' : 'false'; ?>"
								data-record-type="<?php echo htmlspecialchars( $record['type'] ); ?>"
								data-record-name="<?php echo htmlspecialchars( $record['name'] ); ?>"
								data-record-content="<?php echo htmlspecialchars( $record['content'] ); ?>">
								<span class="toggle-status"><?php echo $record['proxied'] ? 'ON' : 'OFF'; ?></span>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

<?php endforeach; ?>

<div class="help">
	<h2>DNS Proxy Status</h2>
	<p>This page shows all A and CNAME records for your zones. Click the toggle button to enable or disable Cloudflare proxy for individual records.</p>
	<p><strong>Proxied (ON):</strong> Traffic is routed through Cloudflare's network, hiding your origin IP and enabling Cloudflare features like DDoS protection and caching.</p>
	<p><strong>Not Proxied (OFF):</strong> DNS only mode - your origin server's IP is exposed and traffic goes directly to your server.</p>
	<p><a target="_blank" href="https://developers.cloudflare.com/dns/manage-dns-records/reference/proxied-dns-records/">Developer Docs</a></p>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
	const toggleButtons = document.querySelectorAll('.proxy-toggle-btn');
	const messageBox = document.getElementById('ajax-message');
	const messageText = document.getElementById('ajax-message-text');

	toggleButtons.forEach(button => {
		button.addEventListener('click', function() {
			const recordId = this.dataset.recordId;
			const zoneId = this.dataset.zoneId;
			const isProxied = this.dataset.proxied === 'true';
			const recordName = this.dataset.recordName;
			const recordType = this.dataset.recordType;
			const recordContent = this.dataset.recordContent;
			const newProxiedValue = !isProxied;

			// Show confirmation
			const action = newProxiedValue ? 'ENABLE' : 'DISABLE';
			const warning = newProxiedValue
				? 'This will route traffic through Cloudflare.'
				: 'This will expose your origin server IP address.';

			if (!confirm(`${action} proxy for ${recordName}?\n\n${warning}`)) {
				return;
			}

			// Show loading state
			this.classList.add('loading');
			this.disabled = true;
			const statusSpan = this.querySelector('.toggle-status');
			const originalText = statusSpan.textContent;
			statusSpan.textContent = '...';

			// Prepare form data
			const formData = new FormData();
			formData.append('zone_id', zoneId);
			formData.append('record_id', recordId);
			formData.append('setting', 'dns_record_proxy');
			formData.append('value', newProxiedValue ? 'true' : 'false');
			formData.append('record_type', recordType);
			formData.append('record_name', recordName);
			formData.append('record_content', recordContent);

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
				this.classList.remove('loading');
				this.disabled = false;

				if (data.success) {
					// Update button state
					this.dataset.proxied = newProxiedValue ? 'true' : 'false';
					this.className = 'proxy-toggle-btn ' + (newProxiedValue ? 'proxied' : 'not-proxied');
					statusSpan.textContent = newProxiedValue ? 'ON' : 'OFF';

					// Show success message
					messageBox.className = 'notice notice-success';
					messageText.textContent = data.message;
				} else {
					// Restore original state
					statusSpan.textContent = originalText;
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
				this.disabled = false;
				statusSpan.textContent = originalText;

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
