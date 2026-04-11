<?php
/**
 * View Rulesets page — landing page showing all defined WAF rulesets.
 * No API calls; reads only from rules.php (already loaded by index.php).
 */

// Direct access protection
defined( 'CLOUDFLARE_API_KEY' ) || exit( 'No direct script access allowed' );
?>

<h2>WAF Rulesets</h2>
<p class="page-intro">Review all available WAF rulesets before applying them to domains.</p>

<?php foreach ( $rulesets as $ruleset_key => $ruleset ) : ?>
	<?php $rules_list = array_values( $ruleset['rules'] ); ?>

	<div class="ruleset-section">
		<h2>
			<?php echo htmlspecialchars( $ruleset['description'], ENT_QUOTES, 'UTF-8' ); ?>
			<code class="ruleset-key"><?php echo htmlspecialchars( $ruleset_key, ENT_QUOTES, 'UTF-8' ); ?></code>
		</h2>

		<div class="ruleset-rules-list">
				<?php foreach ( $rules_list as $idx => $rule ) : ?>
					<?php
					$description = isset( $rule['description'] ) ? $rule['description'] : '(unnamed)';
					$rule_action = isset( $rule['action'] ) ? $rule['action'] : '—';
					$expression  = isset( $rule['expression'] ) ? $rule['expression'] : '—';
					?>
					<div class="ruleset-rule-item">
						<div class="rule-meta">
							<span class="rule-num"><?php echo ( $idx + 1 ); ?>.</span>
							<strong class="rule-description"><?php echo htmlspecialchars( $description, ENT_QUOTES, 'UTF-8' ); ?></strong>
							<span class="action-badge action-<?php echo htmlspecialchars( $rule_action, ENT_QUOTES, 'UTF-8' ); ?>">
								<?php echo htmlspecialchars( $rule_action, ENT_QUOTES, 'UTF-8' ); ?>
							</span>
						</div>
						<?php if ( ! empty( $rule['notes'] ) ) : ?>
							<p class="rule-notes"><?php echo htmlspecialchars( $rule['notes'], ENT_QUOTES, 'UTF-8' ); ?></p>
						<?php endif; ?>
						<pre class="expression-text"><?php echo htmlspecialchars( $expression, ENT_QUOTES, 'UTF-8' ); ?></pre>
					</div>
				<?php endforeach; ?>
			</div>
	</div>
<?php endforeach; ?>
