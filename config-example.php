<?php
// Cloudflare API configuration
define( 'CLOUDFLARE_API_KEY', 'your_api_key_here' );
define( 'CLOUDFLARE_EMAIL', 'your_email_here' );
define(
	'CLOUDFLARE_ACCOUNT_IDS',
	array(
		'account_id_1_here',
		'account_id_2_here',
		'account_id_3_here',
	// Add more account IDs as needed
	)
);

// Human-readable nicknames for each account — must match the order of CLOUDFLARE_ACCOUNT_IDS.
// Used to name API token files (e.g. cloudflare-api-key-client-one).
define(
	'CLOUDFLARE_ACCOUNT_NAMES',
	array(
		'Client One',
		'Client Two',
		'Client Three',
	// Add more names as needed
	)
);

// Fail2ban integration settings
// See cloudflare-fail2ban README for full setup instructions.
define( 'FAIL2BAN_LIST_ID', 'cf_fail2ban_blocked' );

// Local path where limited-scope API token files will be written.
// This path must be writable by your web server process.
// Token files are deployed to your server(s) via SCP — they are NEVER sent over HTTP.
define( 'FAIL2BAN_TOKEN_PATH', '/Users/your-username/.cloudflare' );

// Servers to generate configuration files for.
// Add one entry per Plesk/Ubuntu server running fail2ban.
define(
	'FAIL2BAN_SERVERS',
	array(
		array(
			'name'     => 'Server 1',
			'hostname' => 'your-server.example.com',
			'ssh_user' => 'root',
		),
		// array(
		// 'name'     => 'Server 2',
		// 'hostname' => 'staging.example.com',
		// ),
	)
);
