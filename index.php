<?php
/**
 * Cloudflare WAF Rules Wizard Standalone
 *
 * This script is a standalone version of the Cloudflare WAF Rules Wizard plugin.
 * It allows users to create and manage custom WAF rules for their Cloudflare accounts.
 *
 * @package   CloudflareWAFRulesWizard
 * @version   1.0
 * @category  Cloudflare
 * @author    Peter Wise, Square Candy; Troy Glancy, Web Agency Hero; Rob Marlbrough, Press Wizards
 * @license   GPL-3.0-or-later
 * @link      https://example.com
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

		if ( ( isset( $_POST['pw_create_ruleset'] ) || isset( $_POST['pw_test_ruleset'] ) ) && isset( $_POST['pw_ruleset'] ) && ! empty( $_POST['pw_ruleset'] ) ) {
			$ruleset_name = $_POST['pw_ruleset'];
			$ruleset_name = preg_replace( '/[^a-z0-9_]/', '_', $ruleset_name );

			if ( isset( $rulesets[ $ruleset_name ] ) ) {
				$rules = $rulesets[ $ruleset_name ]['rules'];
				if ( isset( $_POST['pw_test_ruleset'] ) ) {
					foreach ( $rules as $rule ) {
						echo '<h2>' . $rule['description'] . '<br>' . $rule['action'] . '</h2>';
						echo '<textarea>' . $rule['expression'] . '</textarea>';
					}
				} elseif ( isset( $_POST['pw_create_ruleset'] ) ) {
					pw_cloudflare_ruleset_manager_process_zones( $rules );
				}
			} else {
				echo '<div class="notice notice-error"><p>Invalid ruleset selected.</p></div>';
			}
		}
		?>
		<form method="post">
			<?php
			$zones = pw_get_cloudflare_zones(
				CLOUDFLARE_ACCOUNT_IDS,
				CLOUDFLARE_API_KEY,
				CLOUDFLARE_EMAIL
			);
			?>
			<h2>Select Ruleset to Apply:</h2>
			<select name="pw_ruleset">
				<?php foreach ( $rulesets as $ruleset_key => $ruleset ) : ?>
					<option value="<?php echo $ruleset_key; ?>">
						<?php echo $ruleset['description']; ?>
					</option>
				<?php endforeach; ?>
			</select>
			<br/>
			<h2>Select Domains to Reset WAF Custom Rules on:</h2>
			<?php foreach ( $zones as $zone ) : ?>
				<label>
					<input type="checkbox" name="pw_zone_ids[]" value="<?php echo $zone['id']; ?>">
					<?php echo $zone['name']; ?>
				</label><br>
			<?php endforeach; ?>
			<br/>
			<input type="submit" class="button button-primary" name="pw_create_ruleset" value="Create/Overwrite All WAF Rules"><br><br>
			<input type="submit" class="button button-secondary" name="pw_test_ruleset" value="Test Ruleset">
		</form>
		<hr>
		<p>&nbsp;</p>
		<p>&nbsp;</p>
		<p><small>Cloudflare Rules Based on <a target="_blank" href="https://webagencyhero.com/cloudflare-waf-rules-v3/">Troy Glancy's superb Cloudflare WAF Rules v3</a></small></p>
		<p><small>Standalone PHP version based on <a target="_blank" href="https://github.com/presswizards/cloudflare-waf-rules-wizard">Rob Marlbrough (Press Wizards) Cloudflare WAF Rules Wizard Plugin</a></small></p>
	</div>
</body>
<?php
