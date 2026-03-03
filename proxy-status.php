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

<div class="backup-controls">
	<button id="create-backup-btn" class="button-primary">
		<span class="btn-icon">💾</span> Create Backup
	</button>
	<button id="disable-all-btn" class="button-danger" disabled>
		<span class="btn-icon">🚫</span> Disable All Proxy
	</button>
	<button id="restore-backup-btn" class="button-secondary">
		<span class="btn-icon">⏮</span> Restore Backup
	</button>
</div>

<div id="restore-modal" class="modal hidden">
	<div class="modal-content">
		<div class="modal-header">
			<h3>Restore from Backup</h3>
			<button class="modal-close">&times;</button>
		</div>
		<div class="modal-body">
			<p>Select a backup to restore:</p>
			<div id="backup-list" class="backup-list">
				<p class="loading-message">Loading backups...</p>
			</div>
		</div>
	</div>
</div>

<?php
$zones = pw_get_cloudflare_zones(
	CLOUDFLARE_ACCOUNT_IDS,
	CLOUDFLARE_API_KEY,
	CLOUDFLARE_EMAIL
);

foreach ( $zones as $zone ) :
	$zone_id     = $zone['id'];
	$dns_records = pw_get_zone_dns_records(
		$zone_id,
		CLOUDFLARE_API_KEY,
		CLOUDFLARE_EMAIL
	);
	?>

	<div class="zone-section" data-zone-id="<?php echo htmlspecialchars( $zone_id ); ?>">
		<div class="zone-header">
			<div class="zone-info">
				<h3><?php echo htmlspecialchars( $zone['name'] ); ?></h3>
				<p class="zone-id-display">Zone ID: <code><?php echo htmlspecialchars( $zone_id ); ?></code></p>
			</div>
			<div class="zone-controls">
				<button class="zone-disable-btn button-danger-small" data-zone-id="<?php echo htmlspecialchars( $zone_id ); ?>" data-zone-name="<?php echo htmlspecialchars( $zone['name'] ); ?>" disabled>
					<span class="btn-icon">🚫</span> Disable All
				</button>
				<button class="zone-restore-btn button-secondary-small" data-zone-id="<?php echo htmlspecialchars( $zone_id ); ?>" data-zone-name="<?php echo htmlspecialchars( $zone['name'] ); ?>">
					<span class="btn-icon">⏮</span> Restore
				</button>
			</div>
		</div>

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
	</div>

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

	// Backup and Restore functionality
	const createBackupBtn = document.getElementById('create-backup-btn');
	const disableAllBtn = document.getElementById('disable-all-btn');
	const restoreBackupBtn = document.getElementById('restore-backup-btn');
	const restoreModal = document.getElementById('restore-modal');
	const modalClose = document.querySelector('.modal-close');
	const backupList = document.getElementById('backup-list');

	// Load backups and update UI state
	function loadBackups() {
		const formData = new FormData();
		formData.append('setting', 'get_backups');

		fetch('ajax-handler.php', {
			method: 'POST',
			body: formData,
			headers: {
				'X-Requested-With': 'XMLHttpRequest'
			}
		})
		.then(response => response.json())
		.then(data => {
			if (data.success) {
				// Update disable all button state
				disableAllBtn.disabled = !data.can_disable_all;
				if (data.can_disable_all) {
					const timeRemaining = Math.round((data.latest_backup_time + 3600 - Math.floor(Date.now() / 1000)) / 60);
					disableAllBtn.title = `Backup created recently (${timeRemaining} minutes remaining)`;
				} else {
					disableAllBtn.title = 'Create a backup first (valid for 1 hour)';
				}

				// Update zone-level button states
				updateZoneButtonStates(data.can_disable_all);

				// Populate backup list
				if (data.backups.length === 0) {
					backupList.innerHTML = '<p class="no-backups">No backups found</p>';
				} else {
					let html = '<div class="backup-items">';
					data.backups.forEach(backup => {
						const date = new Date(backup.timestamp * 1000);
						const dateStr = date.toLocaleString();
						html += `
							<div class="backup-item">
								<div class="backup-info">
									<strong>${dateStr}</strong>
									<span class="backup-zones">${backup.zones} zone(s)</span>
								</div>
								<button class="button-secondary restore-btn" data-filename="${backup.filename}">
									Restore
								</button>
							</div>
						`;
					});
					html += '</div>';
					backupList.innerHTML = html;

					// Add click handlers to restore buttons
					document.querySelectorAll('.restore-btn').forEach(btn => {
						btn.addEventListener('click', function() {
							const filename = this.dataset.filename;
							if (confirm(`Restore proxy settings from this backup?\n\nThis will update DNS records to match the backup state.`)) {
								restoreBackup(filename);
							}
						});
					});
				}
			}
		})
		.catch(error => {
			console.error('Error loading backups:', error);
			backupList.innerHTML = '<p class="error-message">Error loading backups</p>';
		});
	}

	// Create backup
	createBackupBtn.addEventListener('click', function() {
		if (!confirm('Create a backup of current DNS proxy settings for all zones?')) {
			return;
		}

		this.disabled = true;
		this.textContent = 'Creating...';

		const formData = new FormData();
		formData.append('setting', 'create_backup');

		fetch('ajax-handler.php', {
			method: 'POST',
			body: formData,
			headers: {
				'X-Requested-With': 'XMLHttpRequest'
			}
		})
		.then(response => response.json())
		.then(data => {
			this.disabled = false;
			this.innerHTML = '<span class="btn-icon">💾</span> Create Backup';

			if (data.success) {
				messageBox.className = 'notice notice-success';
				messageText.textContent = data.message;
				loadBackups(); // Refresh backup list and button states
			} else {
				messageBox.className = 'notice notice-error';
				messageText.textContent = data.message || 'Failed to create backup';
			}

			setTimeout(() => {
				messageBox.className = 'notice hidden';
			}, 7 * 1000);
		})
		.catch(error => {
			console.error('Error:', error);
			this.disabled = false;
			this.innerHTML = '<span class="btn-icon">💾</span> Create Backup';
			messageBox.className = 'notice notice-error';
			messageText.textContent = 'Network error occurred';
			setTimeout(() => {
				messageBox.className = 'notice hidden';
			}, 7 * 1000);
		});
	});

	// Disable all proxy
	disableAllBtn.addEventListener('click', function() {
		if (!confirm('DISABLE proxy for ALL DNS records in ALL zones?\n\nThis will expose your origin server IP addresses.\n\nThis action requires a recent backup (within 1 hour).')) {
			return;
		}

		this.disabled = true;
		this.textContent = 'Disabling...';

		const formData = new FormData();
		formData.append('setting', 'disable_all');

		fetch('ajax-handler.php', {
			method: 'POST',
			body: formData,
			headers: {
				'X-Requested-With': 'XMLHttpRequest'
			}
		})
		.then(response => response.json())
		.then(data => {
			this.innerHTML = '<span class="btn-icon">🚫</span> Disable All Proxy';

			if (data.success) {
				messageBox.className = 'notice notice-success';
				messageText.textContent = data.message;
				// Reload page to show updated status
				setTimeout(() => {
					location.reload();
				}, 2000);
			} else {
				this.disabled = false;
				messageBox.className = 'notice notice-error';
				messageText.textContent = data.message || 'Failed to disable all';
			}

			setTimeout(() => {
				messageBox.className = 'notice hidden';
			}, 7 * 1000);
		})
		.catch(error => {
			console.error('Error:', error);
			this.disabled = false;
			this.innerHTML = '<span class="btn-icon">🚫</span> Disable All Proxy';
			messageBox.className = 'notice notice-error';
			messageText.textContent = 'Network error occurred';
			setTimeout(() => {
				messageBox.className = 'notice hidden';
			}, 7 * 1000);
		});
	});

	// Show restore modal
	restoreBackupBtn.addEventListener('click', function() {
		restoreModal.classList.remove('hidden');
		loadBackups();
	});

	// Close modal
	modalClose.addEventListener('click', function() {
		restoreModal.classList.add('hidden');
	});

	// Close modal on outside click
	restoreModal.addEventListener('click', function(e) {
		if (e.target === restoreModal) {
			restoreModal.classList.add('hidden');
		}
	});

	// Restore from backup
	function restoreBackup(filename) {
		const formData = new FormData();
		formData.append('setting', 'restore_backup');
		formData.append('filename', filename);

		// Show loading in modal
		backupList.innerHTML = '<p class="loading-message">Restoring...</p>';

		fetch('ajax-handler.php', {
			method: 'POST',
			body: formData,
			headers: {
				'X-Requested-With': 'XMLHttpRequest'
			}
		})
		.then(response => response.json())
		.then(data => {
			if (data.success) {
				messageBox.className = 'notice notice-success';
				messageText.textContent = data.message;
				restoreModal.classList.add('hidden');
				// Reload page to show updated status
				setTimeout(() => {
					location.reload();
				}, 2000);
			} else {
				messageBox.className = 'notice notice-error';
				messageText.textContent = data.message || 'Failed to restore backup';
				loadBackups(); // Reload backup list
			}

			setTimeout(() => {
				messageBox.className = 'notice hidden';
			}, 7 * 1000);
		})
		.catch(error => {
			console.error('Error:', error);
			messageBox.className = 'notice notice-error';
			messageText.textContent = 'Network error occurred';
			loadBackups(); // Reload backup list
			setTimeout(() => {
				messageBox.className = 'notice hidden';
			}, 7 * 1000);
		});
	}

	// Initial load of backups to set button states
	loadBackups();

	// Zone-level controls
	const zoneDisableButtons = document.querySelectorAll('.zone-disable-btn');
	const zoneRestoreButtons = document.querySelectorAll('.zone-restore-btn');

	// Update zone button states based on backup availability
	function updateZoneButtonStates(canDisableAll) {
		zoneDisableButtons.forEach(btn => {
			btn.disabled = !canDisableAll;
			if (canDisableAll) {
				btn.title = 'Disable all proxy for this zone';
			} else {
				btn.title = 'Create a backup first (valid for 1 hour)';
			}
		});
	}

	// Disable all for a specific zone
	zoneDisableButtons.forEach(btn => {
		btn.addEventListener('click', function() {
			const zoneId = this.dataset.zoneId;
			const zoneName = this.dataset.zoneName;

			if (!confirm(`DISABLE proxy for ALL DNS records in ${zoneName}?\n\nThis will expose your origin server IP address for this zone.\n\nThis action requires a recent backup (within 1 hour).`)) {
				return;
			}

			this.disabled = true;
			const originalHtml = this.innerHTML;
			this.textContent = 'Disabling...';

			const formData = new FormData();
			formData.append('setting', 'disable_zone');
			formData.append('zone_id', zoneId);

			fetch('ajax-handler.php', {
				method: 'POST',
				body: formData,
				headers: {
					'X-Requested-With': 'XMLHttpRequest'
				}
			})
			.then(response => response.json())
			.then(data => {
				this.innerHTML = originalHtml;
				this.disabled = false;

				if (data.success) {
					messageBox.className = 'notice notice-success';
					messageText.textContent = `${zoneName}: ${data.message}`;

					// Update all toggles in this zone to OFF
					const zoneSection = this.closest('.zone-section');
					const toggleButtons = zoneSection.querySelectorAll('.proxy-toggle-btn');
					toggleButtons.forEach(btn => {
						if (btn.dataset.proxied === 'true') {
							btn.dataset.proxied = 'false';
							btn.classList.remove('proxied');
							btn.classList.add('not-proxied');
							const statusSpan = btn.querySelector('.toggle-status');
							if (statusSpan) {
								statusSpan.textContent = 'OFF';
							}
						}
					});
				} else {
					messageBox.className = 'notice notice-error';
					messageText.textContent = data.message || 'Failed to disable zone';
				}

				setTimeout(() => {
					messageBox.className = 'notice hidden';
				}, 7 * 1000);
			})
			.catch(error => {
				console.error('Error:', error);
				this.disabled = false;
				this.innerHTML = originalHtml;
				messageBox.className = 'notice notice-error';
				messageText.textContent = 'Network error occurred';
				setTimeout(() => {
					messageBox.className = 'notice hidden';
				}, 7 * 1000);
			});
		});
	});

	// Restore a specific zone from backup
	zoneRestoreButtons.forEach(btn => {
		btn.addEventListener('click', function() {
			const zoneId = this.dataset.zoneId;
			const zoneName = this.dataset.zoneName;

			// Get available backups
			const formData = new FormData();
			formData.append('setting', 'get_backups');

			fetch('ajax-handler.php', {
				method: 'POST',
				body: formData,
				headers: {
					'X-Requested-With': 'XMLHttpRequest'
				}
			})
			.then(response => response.json())
			.then(data => {
				if (!data.success || data.backups.length === 0) {
					alert('No backups available. Please create a backup first.');
					return;
				}

				// Show backup selection modal
				let backupOptions = data.backups.map((backup, index) => {
					const date = new Date(backup.timestamp * 1000);
					const dateStr = date.toLocaleString();
					return `${index + 1}. ${dateStr} (${backup.zones} zones)`;
				}).join('\n');

				const selection = prompt(`Select backup to restore ${zoneName} from:\n\n${backupOptions}\n\nEnter backup number (1-${data.backups.length}):`);

				if (selection === null) return; // User cancelled

				const backupIndex = parseInt(selection) - 1;
				if (isNaN(backupIndex) || backupIndex < 0 || backupIndex >= data.backups.length) {
					alert('Invalid selection');
					return;
				}

				const selectedBackup = data.backups[backupIndex];

				if (!confirm(`Restore ${zoneName} from backup created on ${new Date(selectedBackup.timestamp * 1000).toLocaleString()}?`)) {
					return;
				}

				// Perform restore
				this.disabled = true;
				const originalHtml = this.innerHTML;
				this.textContent = 'Restoring...';

				const restoreFormData = new FormData();
				restoreFormData.append('setting', 'restore_zone');
				restoreFormData.append('zone_id', zoneId);
				restoreFormData.append('filename', selectedBackup.filename);

				fetch('ajax-handler.php', {
					method: 'POST',
					body: restoreFormData,
					headers: {
						'X-Requested-With': 'XMLHttpRequest'
					}
				})
				.then(response => response.json())
				.then(restoreData => {
					this.innerHTML = originalHtml;
					this.disabled = false;

					if (restoreData.success) {
						messageBox.className = 'notice notice-success';
						messageText.textContent = `${zoneName}: ${restoreData.message}`;

						// Update all toggles in this zone based on restored state
						const zoneSection = this.closest('.zone-section');

						// Get the restored state from the response
						if (restoreData.records) {
							restoreData.records.forEach(record => {
								const toggleBtn = zoneSection.querySelector(`button[data-record-id="${record.id}"]`);
								if (toggleBtn) {
									toggleBtn.dataset.proxied = record.proxied ? 'true' : 'false';
									toggleBtn.classList.toggle('proxied', record.proxied);
									toggleBtn.classList.toggle('not-proxied', !record.proxied);
									const statusSpan = toggleBtn.querySelector('.toggle-status');
									if (statusSpan) {
										statusSpan.textContent = record.proxied ? 'ON' : 'OFF';
									}
								}
							});
						}
					} else {
						messageBox.className = 'notice notice-error';
						messageText.textContent = restoreData.message || 'Failed to restore zone';
					}

					setTimeout(() => {
						messageBox.className = 'notice hidden';
					}, 7 * 1000);
				})
				.catch(error => {
					console.error('Error:', error);
					this.disabled = false;
					this.innerHTML = originalHtml;
					messageBox.className = 'notice notice-error';
					messageText.textContent = 'Network error occurred';
					setTimeout(() => {
						messageBox.className = 'notice hidden';
					}, 7 * 1000);
				});
			})
			.catch(error => {
				console.error('Error fetching backups:', error);
				alert('Error loading backups');
			});
		});
	});

	// Initial load of backups (which will also update zone button states)
	loadBackups();
});
</script>
