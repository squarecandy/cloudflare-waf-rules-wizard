<?php
/**
 * Nginx Rules page
 * Regenerates nginx/bot-blocking.conf and nginx/bot-blocking-wp-login.conf
 * on every page load, then displays both for copy/paste.
 */

defined( 'CLOUDFLARE_API_KEY' ) || exit( 'No direct script access allowed' );

// ── Regenerate both conf files ────────────────────────────────────────────────
require_once __DIR__ . '/generate-nginx-rules.php';

// ── Read generated files ──────────────────────────────────────────────────────
$nginx_dir        = __DIR__ . '/nginx';
$file_standard    = $nginx_dir . '/bot-blocking.conf';
$file_wp_login    = $nginx_dir . '/bot-blocking-wp-login.conf';

$conf_standard = file_exists( $file_standard ) ? file_get_contents( $file_standard ) : '(file not found)';
$conf_wp_login = file_exists( $file_wp_login ) ? file_get_contents( $file_wp_login ) : '(file not found)';
$generated_at  = file_exists( $file_standard ) ? date( 'Y-m-d H:i:s', filemtime( $file_standard ) ) : 'unknown';
?>

<h2>Nginx Bot-Blocking Rules</h2>
<p>Rules are regenerated from <code>rules.php</code> each time this page is loaded. Copy the appropriate file contents into your nginx server block via an <code>include</code> directive.</p>
<p class="nginx-meta">Last generated: <strong><?php echo htmlspecialchars( $generated_at, ENT_QUOTES, 'UTF-8' ); ?></strong></p>

<div class="nginx-tabs">
	<div class="nginx-tab-buttons">
		<button class="nginx-tab-btn active" data-tab="standard">
			<code>bot-blocking.conf</code>
			<span class="nginx-tab-desc">Safe for any site — wp-login block disabled</span>
		</button>
		<button class="nginx-tab-btn" data-tab="wplogin">
			<code>bot-blocking-wp-login.conf</code>
			<span class="nginx-tab-desc">Includes wp-login block — requires WPS Hide Login</span>
		</button>
	</div>

	<div class="nginx-tab-panel active" id="nginx-tab-standard">
		<div class="nginx-copy-row">
			<button class="button-primary nginx-copy-btn" data-target="nginx-conf-standard">Copy to Clipboard</button>
			<span class="nginx-copy-confirm" id="nginx-copy-confirm-standard"></span>
		</div>
		<textarea id="nginx-conf-standard" class="nginx-conf-textarea" readonly spellcheck="false"><?php echo htmlspecialchars( $conf_standard, ENT_QUOTES, 'UTF-8' ); ?></textarea>
	</div>

	<div class="nginx-tab-panel" id="nginx-tab-wplogin">
		<div class="nginx-copy-row">
			<button class="button-primary nginx-copy-btn" data-target="nginx-conf-wplogin">Copy to Clipboard</button>
			<span class="nginx-copy-confirm" id="nginx-copy-confirm-wplogin"></span>
		</div>
		<textarea id="nginx-conf-wplogin" class="nginx-conf-textarea" readonly spellcheck="false"><?php echo htmlspecialchars( $conf_wp_login, ENT_QUOTES, 'UTF-8' ); ?></textarea>
	</div>
</div>

<style>
.nginx-meta {
	color: #666;
	font-size: 0.9em;
	margin-bottom: 1.5em;
}
.nginx-tabs {
	margin-top: 1em;
}
.nginx-tab-buttons {
	display: flex;
	gap: 0.5em;
	flex-wrap: wrap;
	margin-bottom: 0;
}
.nginx-tab-btn {
	background: #e2e2e2;
	color: #333;
	border: 2px solid #bbb;
	border-bottom: none;
	border-radius: 4px 4px 0 0;
	padding: 0.5em 1em 0.6em;
	cursor: pointer;
	font-size: 0.9em;
	font-weight: 400;
	display: flex;
	flex-direction: column;
	align-items: flex-start;
	gap: 0.15em;
	transition: background 0.15s;
}
.nginx-tab-btn code {
	font-size: 1em;
	font-weight: 700;
}
.nginx-tab-desc {
	font-size: 0.8em;
	color: #666;
}
.nginx-tab-btn.active {
	background: #fff;
	border-color: #333;
	color: #000;
	position: relative;
	z-index: 1;
}
.nginx-tab-btn.active .nginx-tab-desc {
	color: #444;
}
.nginx-tab-panel {
	display: none;
	border: 2px solid #333;
	border-radius: 0 4px 4px 4px;
	padding: 1em;
	background: #fff;
}
.nginx-tab-panel.active {
	display: block;
}
.nginx-copy-row {
	display: flex;
	align-items: center;
	gap: 1em;
	margin-bottom: 0.75em;
}
.nginx-copy-confirm {
	font-size: 0.9em;
	color: #175319;
	font-weight: 600;
	opacity: 0;
	transition: opacity 0.3s;
}
.nginx-copy-confirm.visible {
	opacity: 1;
}
.nginx-conf-textarea {
	width: 100%;
	height: 32em;
	font-family: ui-monospace, "SF Mono", Menlo, Consolas, monospace;
	font-size: 0.8em;
	line-height: 1.5;
	background: #f6f6f6;
	border: 1px solid #ccc;
	border-radius: 3px;
	padding: 0.75em;
	resize: vertical;
	box-sizing: border-box;
	margin: 0;
}
</style>

<script>
(function () {
	// Tab switching
	document.querySelectorAll('.nginx-tab-btn').forEach(function (btn) {
		btn.addEventListener('click', function () {
			document.querySelectorAll('.nginx-tab-btn').forEach(function (b) { b.classList.remove('active'); });
			document.querySelectorAll('.nginx-tab-panel').forEach(function (p) { p.classList.remove('active'); });
			btn.classList.add('active');
			document.getElementById('nginx-tab-' + btn.dataset.tab).classList.add('active');
		});
	});

	// Copy to clipboard
	document.querySelectorAll('.nginx-copy-btn').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var textarea = document.getElementById(btn.dataset.target);
			var confirmEl = document.getElementById('nginx-copy-confirm-' + btn.dataset.target.replace('nginx-conf-', ''));
			navigator.clipboard.writeText(textarea.value).then(function () {
				confirmEl.textContent = '✓ Copied!';
				confirmEl.classList.add('visible');
				setTimeout(function () { confirmEl.classList.remove('visible'); }, 2500);
			});
		});
	});
}());
</script>
