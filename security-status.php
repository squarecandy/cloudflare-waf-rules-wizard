<?php
/**
 * Security Features Status page
 */

// Direct access protection
defined( 'CLOUDFLARE_API_KEY' ) || exit( 'No direct script access allowed' );
?>

<h2>Cloudflare Security Features Status</h2>

<table class="security-table">
	<thead>
		<tr>
			<th>Domain</th>
			<th>Bot Fight Mode</th>
			<th>Block AI Bots</th>
			<th>AI Labyrinth</th>
			<th>Override robots.txt</th>
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
		<tr>
			<td class="zone_domain"><?php echo $zone['name']; ?></td>
			<td class="<?php echo strtolower( $settings['bot_fight_mode'] ); ?>"><?php echo $settings['bot_fight_mode']; ?></td>
			<td class="<?php echo strtolower( $settings['block_ai_bots'] ); ?>"><?php echo $settings['block_ai_bots']; ?></td>
			<td class="<?php echo strtolower( $settings['ai_labyrinth'] ); ?>"><?php echo $settings['ai_labyrinth']; ?></td>
			<td class="<?php echo strtolower( $settings['robots_management'] ); ?>"><?php echo $settings['robots_management']; ?></td>
		</tr>
		<?php endforeach; ?>
	</tbody>
</table>
