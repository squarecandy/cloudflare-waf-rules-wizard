<?php
/**
 * WAF Rules Manager page
 */

// Direct access protection
defined( 'CLOUDFLARE_API_KEY' ) || exit( 'No direct script access allowed' );

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
			echo '<br><br><hr><br>';
		} elseif ( isset( $_POST['pw_create_ruleset'] ) ) {
			// reset the keys of the rules array (causes json errors with CF API if not done)
			$rules = array_values( $rules );
			// process the rules
			pw_cloudflare_ruleset_manager_process_zones( $rules );
		}
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
