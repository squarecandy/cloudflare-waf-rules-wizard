<?php
/**
 * Fail2ban + Cloudflare Integration Setup Panel
 *
 * Automates Cloudflare-side provisioning for the cloudflare-fail2ban scripts:
 *   - Creates the IP List in each account
 *   - Generates limited-scope API tokens (Account Filter Lists: Edit only)
 *   - Deploys the WAF block rule to managed zones
 *   - Generates server config files and SCP deployment commands
 *
 * SECURITY: Token values are NEVER displayed, logged, or returned via AJAX.
 * They are written directly to FAIL2BAN_TOKEN_PATH (chmod 600) and deployed
 * to servers manually via SCP. The global API key never leaves this tool.
 */

// Pre-compute local state (no API calls needed here).
$fb_list_id       = defined( 'FAIL2BAN_LIST_ID' ) ? FAIL2BAN_LIST_ID : 'cf_fail2ban_blocked';
$fb_token_path    = defined( 'FAIL2BAN_TOKEN_PATH' ) ? FAIL2BAN_TOKEN_PATH : '';
$fb_servers       = defined( 'FAIL2BAN_SERVERS' ) ? FAIL2BAN_SERVERS : array();
$fb_account_ids   = CLOUDFLARE_ACCOUNT_IDS;
$fb_account_names = defined( 'CLOUDFLARE_ACCOUNT_NAMES' ) ? CLOUDFLARE_ACCOUNT_NAMES : array();

// Check token file existence locally (no API call — instant).
$token_file_statuses = array();
foreach ( $fb_account_ids as $idx => $account_id ) {
	$slug                        = pw_get_account_slug( $idx );
	$file                        = rtrim( $fb_token_path, '/' ) . '/cloudflare-api-key-' . $slug;
	$token_file_statuses[ $idx ] = ! empty( $fb_token_path ) && file_exists( $file );
}

$config_missing = empty( $fb_token_path );
?>
<style>
.fail2ban-setup h3 { margin-top: 2em; padding-bottom: 0.4em; border-bottom: 2px solid #e2e6ea; }
.fail2ban-setup h4 { margin-top: 1.4em; }
.fb-badge { display: inline-block; padding: 3px 9px; border-radius: 3px; font-size: 0.82em; font-weight: 600; white-space: nowrap; }
.fb-badge.on  { background: #d4edda; color: #155724; }
.fb-badge.off { background: #f8d7da; color: #721c24; }
.fb-badge.loading { background: #fff3cd; color: #856404; }
.fb-table { width: 100%; border-collapse: collapse; margin: 1em 0; }
.fb-table th,
.fb-table td { padding: 9px 12px; border: 1px solid #dee2e6; text-align: left; vertical-align: middle; }
.fb-table th { background: #f2f4f7; font-weight: 700; }
.fb-table tr:nth-child(even) td { background: #fafbfc; }
.fb-account-block { border: 1px solid #dee2e6; border-radius: 5px; padding: 1em 1.2em; margin: 0.8em 0; }
.fb-account-block-header { display: flex; align-items: center; gap: 0.8em; flex-wrap: wrap; }
.fb-zones-container { margin-top: 0.8em; padding-left: 0.5em; }
.fb-zone-row { display: flex; align-items: center; gap: 0.6em; padding: 4px 0; border-bottom: 1px solid #f0f0f0; flex-wrap: wrap; }
.fb-zone-row:last-child { border-bottom: none; }
.fb-zone-name { min-width: 200px; font-family: monospace; font-size: 0.9em; }
.fb-server-block { border: 1px solid #dee2e6; border-radius: 5px; padding: 1.2em; margin: 1em 0; }
.fb-server-block h4 { margin: 0 0 0.8em; }
pre.fb-code { background: #1e2127; color: #abb2bf; padding: 1em 1.2em; border-radius: 5px; overflow-x: auto; font-size: 0.85em; margin: 0.6em 0; line-height: 1.5; }
.fb-checklist-step { border-left: 3px solid #007bff; padding: 0.8em 1em; margin: 1em 0; background: #f8f9ff; border-radius: 0 4px 4px 0; }
.fb-checklist-step > strong { display: block; margin-bottom: 0.4em; }
.fb-btn { padding: 5px 13px; cursor: pointer; border-radius: 3px; font-size: 0.85em; border: 1px solid #0056b3; background: #007bff; color: #fff; }
.fb-btn:hover:not(:disabled) { background: #0056b3; }
.fb-btn.outline { background: #fff; color: #007bff; border-color: #007bff; }
.fb-btn.outline:hover:not(:disabled) { background: #e8f0fe; }
.fb-btn:disabled { opacity: 0.55; cursor: not-allowed; }
.fb-msg { font-size: 0.85em; margin-left: 6px; }
.fb-msg.ok  { color: #155724; }
.fb-msg.err { color: #721c24; }
code.path { font-size: 0.85em; background: #f0f0f0; padding: 1px 5px; border-radius: 2px; word-break: break-all; }
</style>

<div class="fail2ban-setup">

<p>This panel automates the Cloudflare-side provisioning of the <strong>cloudflare-fail2ban</strong> integration. For each configured Cloudflare account it can:</p>
<ul>
	<li>Create the <code><?php echo htmlspecialchars( $fb_list_id, ENT_QUOTES, 'UTF-8' ); ?></code> IP List</li>
	<li>Generate a <strong>limited-scope API token</strong> (Account Filter Lists: Edit only — nothing else)</li>
	<li>Deploy the WAF block rule to managed zones</li>
</ul>
<p>Token files are written to <code class="path"><?php echo htmlspecialchars( $fb_token_path ? $fb_token_path : '(FAIL2BAN_TOKEN_PATH not set)', ENT_QUOTES, 'UTF-8' ); ?></code> and <strong>never sent over HTTP or displayed on screen</strong>. You deploy them to your server(s) via the SCP commands in Step 3.</p>

<?php if ( $config_missing ) : ?>
<div class="notice notice-error">
	<p><strong>Configuration required:</strong> <code>FAIL2BAN_TOKEN_PATH</code> is not set in <code>config.php</code>. Add it before using this panel — e.g.:<br><code>define( 'FAIL2BAN_TOKEN_PATH', '/Users/your-username/.cloudflare' );</code></p>
</div>
<?php endif; ?>

<?php if ( empty( $fb_servers ) ) : ?>
<div class="notice notice-error">
	<p><strong>No servers configured.</strong> Add <code>FAIL2BAN_SERVERS</code> to <code>config.php</code> — e.g.:<br>
	<code>define( 'FAIL2BAN_SERVERS', array( array( 'name' => 'Production', 'hostname' => 'your-server.example.com' ) ) );</code></p>
</div>
<?php endif; ?>

<!-- ============================================================ -->
<!-- STEP 1: CLOUDFLARE ACCOUNT SETUP (IP LISTS + TOKENS)        -->
<!-- ============================================================ -->
<h3>Step 1: Cloudflare Account Setup</h3>
<p>For each account, create the fail2ban IP List and generate a limited-scope API token. IP List status is loaded live from the Cloudflare API. Token file status is checked locally.</p>

<table class="fb-table">
	<thead>
		<tr>
			<th>Account</th>
			<th>IP List (<code><?php echo htmlspecialchars( $fb_list_id, ENT_QUOTES, 'UTF-8' ); ?></code>)</th>
			<th>Limited Token File</th>
			<th>Actions</th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ( $fb_account_ids as $idx => $account_id ) : ?>
			<?php
			$slug         = pw_get_account_slug( $idx );
			$token_exists = $token_file_statuses[ $idx ];
			$safe_id      = htmlspecialchars( $account_id, ENT_QUOTES, 'UTF-8' );
			$token_file   = rtrim( $fb_token_path, '/' ) . '/cloudflare-api-key-' . $slug;
			$safe_path    = htmlspecialchars( $token_file, ENT_QUOTES, 'UTF-8' );
			$disp_name    = isset( $fb_account_names[ $idx ] ) ? $fb_account_names[ $idx ] : ( 'Account ' . ( $idx + 1 ) );
			?>
		<tr id="fb-account-row-<?php echo $idx; ?>">
			<td>
				<strong><?php echo htmlspecialchars( $disp_name, ENT_QUOTES, 'UTF-8' ); ?></strong><br>
				<small style="font-family:monospace;color:#666"><?php echo htmlspecialchars( substr( $account_id, 0, 8 ), ENT_QUOTES, 'UTF-8' ); ?>...</small>
			</td>
			<td id="fb-list-status-<?php echo $idx; ?>">
				<span class="fb-badge loading">Checking&hellip;</span>
			</td>
			<td id="fb-token-status-<?php echo $idx; ?>">
				<?php if ( $token_exists ) : ?>
				<span class="fb-badge on">&#10003; Saved</span><br>
				<code class="path"><?php echo $safe_path; ?></code>
				<?php else : ?>
				<span class="fb-badge off">&#10007; No file</span>
				<?php endif; ?>
			</td>
			<td>
				<button class="fb-btn outline fb-create-list-btn"
					id="fb-create-list-<?php echo $idx; ?>"
					data-account-id="<?php echo $safe_id; ?>"
					data-idx="<?php echo $idx; ?>"
					style="display:none">
					Create IP List
				</button>
				<?php if ( ! $config_missing ) : ?>
				<button class="fb-btn<?php echo $token_exists ? ' outline' : ''; ?> fb-create-token-btn"
					id="fb-create-token-<?php echo $idx; ?>"
					data-account-id="<?php echo $safe_id; ?>"
					data-idx="<?php echo $idx; ?>"
					data-token-path="<?php echo $safe_path; ?>">
					<?php echo $token_exists ? 'Regenerate Token' : 'Create Token'; ?>
				</button>
				<?php endif; ?>
				<span class="fb-msg" id="fb-account-msg-<?php echo $idx; ?>"></span>
			</td>
		</tr>
		<?php endforeach; ?>
	</tbody>
</table>

<!-- ============================================================ -->
<!-- STEP 2: WAF RULE                                             -->
<!-- ============================================================ -->
<h3>Step 2: WAF Rule</h3>
<p>The fail2ban block expression (<code>ip.src in $<?php echo htmlspecialchars( $fb_list_id, ENT_QUOTES, 'UTF-8' ); ?></code>) is already built into the <strong>Block WP Paths</strong> rule in every standard ruleset. After completing Step 1, go to the <a href="?page=waf-rules">WAF Rules Manager</a> tab and click <strong>Update Rules</strong> for each zone to push the updated ruleset.</p>

<!-- ============================================================ -->
<!-- STEP 3: SERVER CONFIGURATION FILES & DEPLOYMENT             -->
<!-- ============================================================ -->
<h3>Step 3: Server Configuration &amp; Deployment</h3>

<?php if ( empty( $fb_servers ) ) : ?>
<p><em>No servers configured &mdash; add <code>FAIL2BAN_SERVERS</code> to <code>config.php</code>.</em></p>
<?php else : ?>
<p>Download the config file for each server, then run the SCP commands below to deploy it along with the token files. Repeat for each server in your fleet.</p>

	<?php foreach ( $fb_servers as $server ) : ?>
		<?php
		$safe_name      = htmlspecialchars( $server['name'], ENT_QUOTES, 'UTF-8' );
		$safe_hostname  = htmlspecialchars( $server['hostname'], ENT_QUOTES, 'UTF-8' );
		$safe_name_attr = $safe_name; // already escaped for HTML attributes
		$ssh_user       = isset( $server['ssh_user'] ) ? $server['ssh_user'] : 'root';
		$safe_ssh_user  = htmlspecialchars( $ssh_user, ENT_QUOTES, 'UTF-8' );
		?>
<div class="fb-server-block">
	<h4><?php echo $safe_name; ?> <small style="font-weight:normal;color:#666"><?php echo $safe_hostname; ?></small></h4>
	<p>
		<button class="fb-btn fb-download-config-btn" data-server-name="<?php echo $safe_name_attr; ?>">
			&#8659; Download cloudflare-fail2ban-config
		</button>
		<span class="fb-msg" id="fb-dl-msg-<?php echo htmlspecialchars( preg_replace( '/\W/', '-', $server['name'] ), ENT_QUOTES, 'UTF-8' ); ?>"></span>
	</p>

	<p><strong>After downloading, run these commands from your Mac terminal:</strong></p>
	<pre class="fb-code"># 1. Create the destination directory on the server (if it doesn't exist)
ssh <?php echo $safe_ssh_user; ?>@<?php echo $safe_hostname; ?> "mkdir -p ~/.cloudflare && chmod 700 ~/.cloudflare"

# 2. Deploy the config file (move it from ~/Downloads first, or adjust the path)
scp ~/Downloads/cloudflare-fail2ban-config <?php echo $safe_ssh_user; ?>@<?php echo $safe_hostname; ?>:/usr/local/bin/cloudflare-fail2ban/cloudflare-fail2ban-config

# 3. Deploy token files
		<?php foreach ( $fb_account_ids as $idx => $account_id ) : ?>
			<?php
			$slug  = pw_get_account_slug( $idx );
			$local = rtrim( $fb_token_path ? $fb_token_path : '~/.cloudflare', '/' ) . '/cloudflare-api-key-' . $slug;
			?>
scp <?php echo htmlspecialchars( $local, ENT_QUOTES, 'UTF-8' ); ?> <?php echo $safe_ssh_user; ?>@<?php echo $safe_hostname; ?>:~/.cloudflare/
<?php endforeach; ?>

# 4. Lock down permissions on the server
ssh <?php echo $safe_ssh_user; ?>@<?php echo $safe_hostname; ?> "chmod 600 ~/.cloudflare/cloudflare-api-key-*"</pre>
</div>
<?php endforeach; ?>
<?php endif; ?>

<!-- ============================================================ -->
<!-- STEP 4: SERVER SETUP CHECKLIST (MANUAL STEPS)               -->
<!-- ============================================================ -->
<h3>Step 4: Server Setup Checklist</h3>
<p>These one-time steps must be done manually on each Plesk/Ubuntu server. Steps 1&ndash;3 above must be completed first.</p>

<div class="fb-checklist-step">
	<strong>4a &mdash; Install the scripts</strong>
<pre class="fb-code">sudo mkdir -p /usr/local/bin/cloudflare-fail2ban

# Copy scripts from your local dist/cloudflare-fail2ban/ directory via scp or git
sudo cp cloudflare-fail2ban-block  /usr/local/bin/cloudflare-fail2ban/
sudo cp cloudflare-fail2ban-unban  /usr/local/bin/cloudflare-fail2ban/
sudo cp cloudflare-fail2ban-sync   /usr/local/bin/cloudflare-fail2ban/
sudo chmod +x /usr/local/bin/cloudflare-fail2ban/cloudflare-fail2ban-{block,unban,sync}

# Symlinks for convenient access
sudo ln -s /usr/local/bin/cloudflare-fail2ban/cloudflare-fail2ban-block /usr/local/bin/cloudflare-fail2ban-block
sudo ln -s /usr/local/bin/cloudflare-fail2ban/cloudflare-fail2ban-unban /usr/local/bin/cloudflare-fail2ban-unban
sudo ln -s /usr/local/bin/cloudflare-fail2ban/cloudflare-fail2ban-sync  /usr/local/bin/cloudflare-fail2ban-sync</pre>
</div>

<div class="fb-checklist-step">
	<strong>4b &mdash; Install the fail2ban action config</strong>
<pre class="fb-code">sudo cp cloudflare-block.conf /etc/fail2ban/action.d/
# Verify paths look correct
sudo cat /etc/fail2ban/action.d/cloudflare-block.conf</pre>
</div>

<div class="fb-checklist-step">
	<strong>4c &mdash; Add cloudflare-block to your fail2ban jails</strong>
	<p>Edit <code>/etc/fail2ban/jail.local</code>:</p>
<pre class="fb-code">[sshd]
enabled = true
action = %(action_)s
		cloudflare-block

[nginx-http-auth]
enabled = true
action = %(action_)s
		cloudflare-block

# Or apply globally in [DEFAULT] — every jail will block at Cloudflare too:
# [DEFAULT]
# action = %(action_mwl)s
#          cloudflare-block</pre>
</div>

<div class="fb-checklist-step">
	<strong>4d &mdash; Add the periodic sync cron job (safety net)</strong>
<pre class="fb-code">sudo crontab -e
# Add this line — runs every 4 hours to reconcile any missed ban/unban events:
0 */4 * * * /usr/local/bin/cloudflare-fail2ban-sync >> /var/log/cloudflare-fail2ban-sync.log 2>&1</pre>
</div>

<div class="fb-checklist-step">
	<strong>4e &mdash; Restart fail2ban and verify</strong>
<pre class="fb-code">sudo systemctl restart fail2ban
sudo fail2ban-client status</pre>
</div>

<div class="fb-checklist-step">
	<strong>4f &mdash; Test the integration end-to-end</strong>
<pre class="fb-code"># Block a test IP (it will appear in your Cloudflare IP list)
sudo /usr/local/bin/cloudflare-fail2ban-block 198.51.100.1 test-jail

# Check syslog for results
sudo grep cloudflare /var/log/syslog | tail -20

# Remove the test IP
sudo /usr/local/bin/cloudflare-fail2ban-unban 198.51.100.1 test-jail</pre>
</div>

</div><!-- .fail2ban-setup -->

<script>
(function () {
	'use strict';

	// Encode POST body from a plain object.
	function encode(data) {
		return Object.keys(data)
			.map(k => encodeURIComponent(k) + '=' + encodeURIComponent(data[k]))
			.join('&');
	}

	// POST to ajax-handler.php and return parsed JSON.
	function ajax(data) {
		return fetch('ajax-handler.php', {
			method: 'POST',
			headers: {
				'X-Requested-With': 'XMLHttpRequest',
				'Content-Type': 'application/x-www-form-urlencoded',
			},
			body: encode(data),
		}).then(function (r) { return r.json(); });
	}

	function setBadge(el, text, cls) {
		if (!el) return;
		el.innerHTML = '<span class="fb-badge ' + cls + '">' + text + '</span>';
	}

	function setMsg(el, text, cls) {
		if (!el) return;
		el.textContent = text;
		el.className = 'fb-msg ' + (cls || '');
	}

	function escHtml(str) {
		var d = document.createElement('div');
		d.textContent = str;
		return d.innerHTML;
	}

	// Build the account list from PHP — avoids iterating arbitrary DOM attributes.
	var accounts = [
<?php foreach ( $fb_account_ids as $idx => $account_id ) : ?>
		{ idx: <?php echo (int) $idx; ?>, accountId: '<?php echo htmlspecialchars( $account_id, ENT_QUOTES, 'UTF-8' ); ?>' },
<?php endforeach; ?>
	];

	// ---------------------------------------------------------------
	// STEP 1: Load IP-list + token-file status for each account.
	// ---------------------------------------------------------------
	accounts.forEach(function (acct) {
		var listEl = document.getElementById('fb-list-status-' + acct.idx);
		if (!listEl) return;

		ajax({ setting: 'fail2ban_check_list_status', account_id: acct.accountId, account_idx: acct.idx })
			.then(function (data) {
				if (!data.success) {
					setBadge(listEl, '&#10007; API error', 'off');
					return;
				}

				if (data.list_exists) {
					setBadge(listEl, '&#10003; List exists', 'on');
					var btn = document.getElementById('fb-create-list-' + acct.idx);
					if (btn) btn.style.display = 'none';
				} else {
					setBadge(listEl, '&#10007; Missing', 'off');
					var btn = document.getElementById('fb-create-list-' + acct.idx);
					if (btn) btn.style.display = 'inline-block';
				}

				// Refresh token-file badge in case it changed since page load.
				var tokenEl = document.getElementById('fb-token-status-' + acct.idx);
				if (tokenEl && data.token_file_exists === false) {
					var tb = tokenEl.querySelector('.fb-badge');
					if (tb && tb.classList.contains('on')) {
						// Server says file missing — update to reflect live state.
						setBadge(tokenEl, '&#10007; No file', 'off');
					}
				}
			})
			.catch(function () {
				setBadge(listEl, '&#10007; Request failed', 'off');
			});
	});

	// ---------------------------------------------------------------
	// Create IP List button handler.
	// ---------------------------------------------------------------
	document.querySelectorAll('.fb-create-list-btn').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var idx       = btn.dataset.idx;
			var accountId = btn.dataset.accountId;
			var msgEl     = document.getElementById('fb-account-msg-' + idx);
			var listEl    = document.getElementById('fb-list-status-' + idx);

			btn.disabled = true;
			setMsg(msgEl, 'Creating\u2026', '');

			ajax({ setting: 'fail2ban_create_list', account_id: accountId })
				.then(function (data) {
					if (data.success) {
						setBadge(listEl, '&#10003; List exists', 'on');
						btn.style.display = 'none';
						setMsg(msgEl, 'List created.', 'ok');
					} else {
						btn.disabled = false;
						setMsg(msgEl, 'Error: ' + (data.message || 'Unknown'), 'err');
					}
				})
				.catch(function (e) {
					btn.disabled = false;
					setMsg(msgEl, 'Network error: ' + e.message, 'err');
				});
		});
	});

	// ---------------------------------------------------------------
	// Create / Regenerate Token button handler.
	// ---------------------------------------------------------------
	document.querySelectorAll('.fb-create-token-btn').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var idx       = btn.dataset.idx;
			var accountId = btn.dataset.accountId;
			var tokenPath = btn.dataset.tokenPath;
			var msgEl     = document.getElementById('fb-account-msg-' + idx);
			var tokenEl   = document.getElementById('fb-token-status-' + idx);
			var isRegen   = btn.classList.contains('outline');

			var confirmMsg = isRegen
				? 'This will create a NEW Cloudflare API token (Account Filter Lists: Edit only) and OVERWRITE the existing file at:\n\n' + tokenPath + '\n\nThe old token will be invalidated. Continue?'
				: 'This will create a Cloudflare API token (Account Filter Lists: Edit only) and save it to:\n\n' + tokenPath + '\n\nThe token value will NOT be shown — it is written directly to disk. Continue?';

			if (!confirm(confirmMsg)) return;

			btn.disabled = true;
			setMsg(msgEl, 'Creating token\u2026', '');

			ajax({ setting: 'fail2ban_create_token', account_id: accountId, account_idx: idx })
				.then(function (data) {
					if (data.success) {
						// Show only the filepath — NEVER the token value.
						setBadge(tokenEl, '&#10003; Saved', 'on');
						if (data.filepath) {
							var codeEl = tokenEl.querySelector('code.path');
							if (!codeEl) {
								codeEl = document.createElement('code');
								codeEl.className = 'path';
								tokenEl.appendChild(document.createElement('br'));
								tokenEl.appendChild(codeEl);
							}
							codeEl.textContent = data.filepath;
						}
						btn.textContent = 'Regenerate Token';
						btn.classList.add('outline');
						btn.disabled = false;
						setMsg(msgEl, 'Token saved (chmod 600).', 'ok');
					} else {
						btn.disabled = false;
						setMsg(msgEl, 'Error: ' + (data.message || 'Unknown'), 'err');
					}
				})
				.catch(function (e) {
					btn.disabled = false;
					setMsg(msgEl, 'Network error: ' + e.message, 'err');
				});
		});
	});

	// ---------------------------------------------------------------
	// STEP 3: Download config file for a server.
	// ---------------------------------------------------------------
	document.querySelectorAll('.fb-download-config-btn').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var serverName = btn.dataset.serverName;
			var slug       = serverName.replace(/\W/g, '-');
			var msgEl      = document.getElementById('fb-dl-msg-' + slug);

			btn.disabled = true;
			setMsg(msgEl, 'Generating\u2026', '');

			ajax({ setting: 'fail2ban_download_config', server_name: serverName })
				.then(function (data) {
					btn.disabled = false;
					if (!data.success) {
						setMsg(msgEl, 'Error: ' + (data.message || 'Unknown'), 'err');
						return;
					}

					// Trigger a browser download — config content never touches the DOM.
					var blob = new Blob([data.config], { type: 'text/plain' });
					var url  = URL.createObjectURL(blob);
					var a    = document.createElement('a');
					a.href     = url;
					a.download = data.filename;
					document.body.appendChild(a);
					a.click();
					document.body.removeChild(a);
					URL.revokeObjectURL(url);

					setMsg(msgEl, 'Downloaded \u2014 no token values are included.', 'ok');
				})
				.catch(function (e) {
					btn.disabled = false;
					setMsg(msgEl, 'Network error: ' + e.message, 'err');
				});
		});
	});

}());
</script>
<?php
