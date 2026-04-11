<?php
// Cloudflare API configuration
define( 'CLOUDFLARE_API_KEY', 'your_api_key_here' );
define( 'CLOUDFLARE_EMAIL', 'your_email_here' );
// One entry per Cloudflare account — id, human-readable name, and which fail2ban
// server(s) (by FAIL2BAN_SERVERS slug) manage this account's blocked IPs.
// Omit 'servers' on an account to deploy it to every server.
define(
	'CLOUDFLARE_ACCOUNTS',
	array(
		array(
			'id'      => 'account_id_1_here',
			'name'    => 'Client One',
			'servers' => array( 'my-server' ),
		),
		array(
			'id'      => 'account_id_2_here',
			'name'    => 'Client Two',
			'servers' => array( 'my-server' ),
		),
		array(
			'id'      => 'account_id_3_here',
			'name'    => 'Client Three',
			'servers' => array( 'my-server' ),
		),
		// Add more accounts as needed.
	)
);

// Derived — all existing code reads these constants directly and needs no changes.
define( 'CLOUDFLARE_ACCOUNT_IDS', array_column( CLOUDFLARE_ACCOUNTS, 'id' ) );
define( 'CLOUDFLARE_ACCOUNT_NAMES', array_column( CLOUDFLARE_ACCOUNTS, 'name' ) );

// Map each domain to its WAF ruleset key (from the $rulesets array in rules.php).
// Any domain NOT listed here will use the default 'squarecandy_rules' (FREE) ruleset.
// Ruleset keys: squarecandy_rules | squarecandy_rules_ecommerce | squarecandy_rules_pro | squarecandy_rules_drupal
define(
	'ZONE_RULESET_MAP',
	array(
		// 'shop.example.com' => 'squarecandy_rules_ecommerce',
		// 'old-drupal-site.com' => 'squarecandy_rules_drupal',
	)
);

// Fail2ban integration settings
// See cloudflare-fail2ban README for full setup instructions.
define( 'FAIL2BAN_LIST_ID', 'cf_fail2ban_blocked' );

// Local path where limited-scope API token files will be written.
// This path must be writable by your web server process.
// Token files are deployed to your server(s) via SCP — they are NEVER sent over HTTP.
define( 'FAIL2BAN_TOKEN_PATH', '/Users/your-username/.cloudflare' );

// Servers keyed by a short slug — account 'servers' arrays reference these slugs.
define(
	'FAIL2BAN_SERVERS',
	array(
		'my-server' => array(
			'name'     => 'Server 1',
			'hostname' => 'your-server.example.com',
			'ssh_user' => 'root',
		),
		// 'staging' => array(
		//     'name'     => 'Staging',
		//     'hostname' => 'staging.example.com',
		//     'ssh_user' => 'root',
		// ),
	)
);
