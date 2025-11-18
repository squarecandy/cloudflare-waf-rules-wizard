<?php
/**
 * Cloudflare WAF Rules Wizard Standalone
 *
 * This script is a standalone version of the Cloudflare WAF Rules Wizard plugin.
 * It allows users to create and manage custom WAF rules for their Cloudflare accounts.
 *
 * @package   CloudflareWAFRulesWizardStandalone
 * @version   2.0.2
 * @category  Cloudflare
 * @author    Peter Wise, Square Candy; Rob Marlbrough, Press Wizards; Troy Glancy, Web Agency Hero
 * @license   GPL-3.0-or-later
 * @link      https://github.com/squarecandy/cloudflare-waf-rules-wizard
 * @php       7.4
 */

require_once 'config.php';
?>
<html>
<head>
	<title>Cloudflare WAF Rules Wizard</title>
	<link rel="stylesheet" href="style.css">
</head>
<body>
	<div class="container">
		<h1>Cloudflare WAF Rules Wizard</h1>
		<?php
		// Navigation
		$current_page = isset( $_GET['page'] ) ? $_GET['page'] : 'waf-rules';
		?>
		<div class="nav-container">
			<ul class="nav-menu">
				<li class="<?php echo 'waf-rules' === $current_page ? 'active' : ''; ?>">
					<a href="index.php?page=waf-rules">WAF Rules Manager</a>
				</li>
				<li class="<?php echo 'security-status' === $current_page ? 'active' : ''; ?>">
					<a href="index.php?page=security-status">Security Features Status</a>
				</li>
				<li class="<?php echo 'proxy-status' === $current_page ? 'active' : ''; ?>">
					<a href="index.php?page=proxy-status">DNS Proxy Status</a>
				</li>
			</ul>
		</div>
		<?php
		if (
			! defined( 'CLOUDFLARE_API_KEY' ) ||
			! defined( 'CLOUDFLARE_EMAIL' ) ||
			! defined( 'CLOUDFLARE_ACCOUNT_IDS' ) ||
			empty( CLOUDFLARE_API_KEY ) ||
			empty( CLOUDFLARE_EMAIL ) ||
			empty( CLOUDFLARE_ACCOUNT_IDS ) ||
			! is_array( CLOUDFLARE_ACCOUNT_IDS )
		) :
			?>
			<div class="notice notice-error"><p>Please set your Cloudflare API credentials in the config.php file.</p></div>
			</div></body></html>
			<?php
			exit;
		endif;

		require_once 'rules.php';
		require_once 'functions.php';

		// Load the appropriate page content
		if ( 'security-status' === $current_page ) {
			include 'security-status.php';
		} elseif ( 'proxy-status' === $current_page ) {
			include 'proxy-status.php';
		} else { // Default to WAF Rules page
			include 'waf-rules.php';
		}
		?>
		<p>&nbsp;</p>
		<p>&nbsp;</p>
		<p><small>Cloudflare Rules Based on <a target="_blank" href="https://webagencyhero.com/cloudflare-waf-rules-v3/">Troy Glancy's superb Cloudflare WAF Rules v3</a></small></p>
		<p><small>Standalone PHP version based on <a target="_blank" href="https://github.com/presswizards/cloudflare-waf-rules-wizard">Rob Marlbrough (Press Wizards) Cloudflare WAF Rules Wizard Plugin</a></small></p>
	</div>
</body>
</html>
