<?php
///// Rules snippets with explainations /////

// Allow Rules //

// Google
$googlebot = '(cf.verified_bot_category in {"Search Engine Crawler" "Advertising & Marketing" "Page Preview" "Academic Research" "Security" "Accessibility" "Webhooks" "Feed Fetcher" "Aggregator" "Monitoring & Analytics"} and http.user_agent contains "Google")';
//Bing
$bingbot = '(cf.verified_bot_category in {"Search Engine Crawler" "Monitoring & Analytics" "Advertising & Marketing" "Page Preview" "Academic Research" "Security" "Accessibility" "Webhooks" "Feed Fetcher" "Aggregator"} and http.user_agent contains "Bing")';
// Allow DuckDuck
$duckduckgo = '(cf.verified_bot_category eq "Search Engine Crawler" and http.user_agent contains "DuckDuckBot")';
// Allow verified page preview bots
$page_preview = '(cf.verified_bot_category eq "Page Preview")';
// Allow Wayback Machine
$wayback = '(http.user_agent contains "archive.org" and cf.client.bot)';
// Allow Accessibility Bots
$accessibility = '(cf.verified_bot_category eq "Accessibility")';
// Allow Uptime Robot
$uptimerobot = '(cf.verified_bot_category eq "Monitoring & Analytics" and http.user_agent contains "UptimeRobot")';
// Allow Let's Encrypt
$letsencrypt = '(http.user_agent contains "letsencrypt" and http.request.uri.path contains "acme-challenge")';
// Allow Stripe
$stripe = '(cf.verified_bot_category eq "Webhooks" and http.user_agent contains "stripe")';
// Allow EWWW / ExactDN / EasyIO
$ewww = '(http.user_agent contains "ExactDN") or (http.user_agent contains "ewww.io")';
// Allow Square Candy Visual Regression Tools
$visual_regression = '(http.user_agent eq "squarecandy-visual-regression-testing")';
// Allow Patchstack
$patchstack = '(http.cookie contains "skip_splash" and http.request.uri contains "_wcb")';
// Allow Github Actions/Hooks
$github = '(http.user_agent contains "GitHub-Hookshot")';
// Allow Asana Screenshots
$asana = '(http.user_agent contains "Asana/1.4.0 WebsiteMetadataRetriever")';
// Allow Better Uptime (GridPane Uptime Monitoring)
$betteruptime = '(http.user_agent contains "Better Uptime Bot")';
// Allow Zenventory WC API access
$zenventory = '(starts_with(http.request.uri.path, "/wp-json/wc") and http.user_agent contains "Zenventory")';
// Allow Salesforce WooCommerce Calls
$salesforce_wc = '(starts_with(http.request.uri.path, "/wp-json/wc") and http.user_agent contains "SFDC-Callout")';
// Allow Salesforce GiveWP API Calls
$salesforce_give = '(starts_with(http.request.uri.path, "/give-api/donations") and http.user_agent contains "SFDC-Callout")';

// Allow Rules Free
$allow_free            = array(
	$googlebot,
	$bingbot,
	$duckduckgo,
	$page_preview,
	$wayback,
	$accessibility,
	$uptimerobot,
	$letsencrypt,
	$stripe,
	$ewww,
	$visual_regression,
	$patchstack,
	$github,
	$asana,
	$betteruptime,
);
$allow_expression_free = implode( ' or ', $allow_free );

// Allow Rules Ecommerce
$allow_ecommerce = array(
	$zenventory,
	$salesforce_wc,
	$salesforce_give,
);
$allow_ecommerce = array_merge( $allow_free, $allow_ecommerce );

$allow_expression_ecommerce = implode( ' or ', $allow_ecommerce );

// Block or Challenge Rules //

// Challenge/Block Large Cloud Providers (Google Cloud, Amazon EC2, Azure, Etc.)
$cloud_asns = array(
	'7224', // Amazon
	'16509', // Amazon
	'14618', // Amazon
	'15169', // Google
	'8075', // Microsoft
	'396982', // Google Cloud
	'53755', // IOFLOOD
	'268480', // ALTA VELOCIDADE TELECOMUNICACOES (Brazil)
	'262365', // IBI TELECOM (Brazil)
	'206092', // Internet Utilities Europe and Asia Limited (UK)
	// '135061', // UNICOM-ShenZhen-IDC
	// '23724', // China Telecom
	'4134', // China Backbone (unknown probably IA/TikTok)
	'4837', // China Backbone (unknown probably IA/TikTok)
	// '4808', // China Telecom
);
$cloud_asns = implode( ' ', $cloud_asns );

// Challenge/Block Web Hosting ASNs
// @TODO - add comments for what we're actually blocking here...
$web_hosts = array(
	'26496', // GoDaddy
	'398101', // GoDaddy
	'18450', // WebNX
	'50673', // Serverius Holding B.V. (Netherlands)
	'7393', // Cybercon
	'14061',
	'31815',
	'205544',
	'199610',
	'21501',
	'16125',
	'51540',
	'264649',
	'39020',
	'30083',
	'35540',
	'55293',
	'36943',
	'32244',
	'6724',
	'63949',
	'7203',
	'201924',
	'30633',
	'208046',
	'36352',
	'25264',
	'32475',
	'23033',
	'32475',
	'212047',
	'32475',
	'31898',
	'210920',
	'211252',
	'16276',
	'23470',
	'136907',
	'12876',
	'210558',
	'132203',
	'61317',
	'212238',
	'37963',
	'13238',
	'2639',
	'20473',
	'63018',
	'395954',
	'19437',
	'207990',
	'27411',
	'53667',
	'27176',
	'396507',
	'206575',
	'20454',
	'51167',
	'60781',
	'62240',
	'398493',
	'213230',
	'26347',
	'20738',
	'45102',
	'24940',
	'57523',
	'8100',
	'8560',
	'6939',
	'14178',
	'46606',
	'197540',
	'397630',
	'9009',
	'11878',
	'49505',
);
$web_hosts = implode( ' ', $web_hosts );

$challenge_asns = '(ip.src.asnum in {' . $cloud_asns . ' ' . $web_hosts . '} and not cf.client.bot and not cf.verified_bot_category in {"Search Engine Crawler" "Search Engine Optimization" "Monitoring & Analytics" "Advertising & Marketing" "Page Preview" "Academic Research" "Security" "Accessibility" "Webhooks" "Feed Fetcher" "Aggregator"})';


// block agressive crawlers
$aggressive_crawlers = array(
	'advanced email extractor',
	'ahrefsbot',
	'amazonbot',
	'atspider',
	'barkrowler',
	'bwh3_user_agent',
	'bytedance',
	'bytespider',
	'cms spider',
	'contactbot',
	'contentsmartz',
	'datacha0s',
	'dataforseobot',
	'dbrowse ',
	'ebrowse',
	'efp@gmx.net',
	'email_hunter',
	'emailcollector',
	'emailsiphon',
	'emailspider',
	'emailwolf',
	'extractorpro',
	'franklin locator',
	'friendlycrawler',
	'guestbook',
	'indy library',
	'iplexx',
	'isc systems',
	'iupui research',
	'letscrawl.com',
	'lincoln stater',
	'lmqueuebot',
	'Mail.RU_Bot',
	'missauga locate',
	'missouri college browse',
	'mizzu labs',
	'mj12bot',
	'mo college',
	'mvaclient',
	'nasa search',
	'newt activex',
	'nsauditor',
	'pbrowse',
	'petalbot',
	'peval 1.4b',
	'poirot',
	'port huron labs',
	'production bot',
	'program shareware',
	'psycheclone',
	'scan4mail',
	'screaming frog',
	'searchbot admin@google.com',
	'semrushbot',
	'seznambot',
	'shablastbot',
	'snap.com',
	'snapbot',
	'sogou',
	'sohu agent',
	'surf15a',
	'timpibot',
	'trackback',
	'trendictionbot',
	'turnitin',
	'tweetmemebot',
	'vadixbot',
	'webemailextrac',
	'webvulncrawl',
	'yandex',
);

$aggressive_crawlers = array_map(
	function ( $crawler ) {
		return '(lower(http.user_agent) contains "' . $crawler . '")';
	},
	$aggressive_crawlers
);
$aggressive_crawlers = implode( ' or ', $aggressive_crawlers );

// TOR
$tor = '(ip.src.country eq "T1")';
// Block Drupal patterns
$drupal = '(starts_with(http.request.uri.path, "/sites/default/files/")) or (starts_with(http.request.uri.path, "/sites/all/")) or (starts_with(http.request.uri.path, "/node")) or (http.request.full_uri contains "civicrm")';
// Block Sensitive WP Paths
$wp_paths = '(http.request.uri.path contains "xmlrpc") or (http.request.uri.path contains "xmrlpc") or (http.request.uri.path contains "wlwmanifest") or (http.request.uri.path contains "wp-config") or (http.request.uri.path contains "passwd")';
// Block General AI Crawlers and Assistant Bots
$ai_crawlers = '(cf.verified_bot_category in {"AI Crawler" "Other" "AI Assistant"} and not http.user_agent contains "archive.org")';
// Block OpenAI (when they actually reveal their user agent!)
$open_ai = '(http.user_agent contains "openai.com") or (http.user_agent contains "ChatGPT")';



///// End Rules snippets /////

$squarecandy_rules_free = array(
	'good_actors_allow'       => array(
		'description'       => 'Good Actors Allow',
		'expression'        => $allow_expression_free,
		'action'            => 'skip',
		'action_parameters' => array(
			'ruleset'  => 'current',
			'phases'   => array( 'http_ratelimit', 'http_request_sbfm', 'http_request_firewall_managed' ),
			'products' => array( 'uaBlock', 'zoneLockdown', 'waf', 'rateLimit', 'bic', 'hot', 'securityLevel' ),
		),
	),
	'block_paths'             => array(
		'description' => 'Block WP Paths, Druapl, AI Crawlers',
		'expression'  => $wp_paths . ' or ' . $drupal . ' or ' . $ai_crawlers . ' or ' . $open_ai,
		'action'      => 'block',
	),
	'block_crawlers'          => array(
		'description' => 'Block Aggressive Crawlers',
		'expression'  => $aggressive_crawlers,
		'action'      => 'block',
	),
	'managed_challenge_hosts' => array(
		'description' => 'Managed Challenge Web Hosts, Cloud Providers, TOR',
		'expression'  => $challenge_asns . ' or ' . $tor,
		'action'      => 'managed_challenge',
	),
	'login_protection'        => array(
		'description' => 'Login Protection',
		'expression'  => '(http.request.uri.path contains "wp-login.php" and not http.request.uri.query contains "action=logout")',
		'action'      => 'managed_challenge',
	),
);

$squarecandy_rules_free_ecommmerce                                    = $squarecandy_rules_free;
$squarecandy_rules_free_ecommmerce['good_actors_allow']['expression'] = $allow_expression_ecommerce;

$squarecandy_rules_pro = array(
	array(
		'description'       => 'Good Actors Allow',
		'expression'        => $allow_expression_free_ecommerce,
		'action'            => 'skip',
		'action_parameters' => array(
			'ruleset'  => 'current',
			'phases'   => array( 'http_ratelimit', 'http_request_sbfm', 'http_request_firewall_managed' ),
			'products' => array( 'uaBlock', 'zoneLockdown', 'waf', 'rateLimit', 'bic', 'hot', 'securityLevel' ),
		),
	),
	'block_wp_paths'          => array(
		'description' => 'Block WP Paths',
		'expression'  => $wp_paths,
		'action'      => 'block',
	),
	'block_drupal_paths'      => array(
		'description' => 'Block Old Druapl Paths',
		'expression'  => $drupal,
		'action'      => 'block',
	),
	'block_ai'                => array(
		'description' => 'Block AI Crawlers',
		'expression'  => $ai_crawlers . ' or ' . $open_ai,
		'action'      => 'block',
	),
	'block_crawlers'          => array(
		'description' => 'Block Aggressive Crawlers',
		'expression'  => $aggressive_crawlers,
		'action'      => 'block',
	),
	'managed_challenge_hosts' => array(
		'description' => 'Managed Challenge Web Hosts',
		'expression'  => '(ip.src.asnum in {' . $web_hosts . '})',
		'action'      => 'managed_challenge',
	),
	'managed_challenge_cloud' => array(
		'description' => 'Managed Challenge Cloud Providers',
		'expression'  => '(ip.src.asnum in {' . $cloud_asns . '} and not cf.client.bot and not cf.verified_bot_category in {"Search Engine Crawler" "Search Engine Optimization" "Monitoring & Analytics" "Advertising & Marketing" "Page Preview" "Academic Research" "Security" "Accessibility" "Webhooks" "Feed Fetcher" "Aggregator"})',
		'action'      => 'managed_challenge',
	),
	'managed_challenge_hosts' => array(
		'description' => 'Managed Challenge TOR',
		'expression'  => $tor,
		'action'      => 'managed_challenge',
	),
	'login_protection'        => array(
		'description' => 'Login Protection',
		'expression'  => '(http.request.uri.path contains "wp-login.php" and not http.request.uri.query contains "action=logout")',
		'action'      => 'managed_challenge',
	),
);



$presswizards_rules = array(
	array(
		'description'       => 'Good Bots Allow',
		'expression'        => '(cf.client.bot) or (cf.verified_bot_category in {"Search Engine Crawler" "Search Engine Optimization" "Monitoring & Analytics" "Advertising & Marketing" "Page Preview" "Academic Research" "Security" "Accessibility" "Webhooks" "Feed Fetcher"}) or (http.user_agent contains "letsencrypt" and http.request.uri.path contains "acme-challenge") or (http.user_agent contains "ExactDN")',
		'action'            => 'skip',
		'action_parameters' => array(
			'ruleset'  => 'current',
			'phases'   => array( 'http_ratelimit', 'http_request_sbfm', 'http_request_firewall_managed' ),
			'products' => array( 'uaBlock', 'zoneLockdown', 'waf', 'rateLimit', 'bic', 'hot', 'securityLevel' ),
		),
	),
	array(
		'description' => 'MC Providers and Countries',
		'expression'  => '(ip.src.asnum in {7224 16509 14618 15169 8075 396982} and not cf.client.bot and not cf.verified_bot_category in {"Search Engine Crawler" "Search Engine Optimization" "Monitoring & Analytics" "Advertising & Marketing" "Page Preview" "Academic Research" "Security" "Accessibility" "Webhooks" "Feed Fetcher" "Aggregator"}) or (not ip.src.country in {"US"} and not cf.client.bot and not cf.verified_bot_category in {"Search Engine Crawler" "Search Engine Optimization" "Monitoring & Analytics" "Advertising & Marketing" "Page Preview" "Academic Research" "Security" "Accessibility" "Webhooks" "Feed Fetcher" "Aggregator"} and not http.request.uri.path contains "acme-challenge")',
		'action'      => 'managed_challenge',
	),
	array(
		'description' => 'MC Aggressive Crawlers',
		'expression'  => '(http.user_agent contains "yandex") or (http.user_agent contains "sogou") or (http.user_agent contains "semrush") or (http.user_agent contains "ahrefs") or (http.user_agent contains "baidu") or (http.user_agent contains "python-requests") or (http.user_agent contains "neevabot") or (http.user_agent contains "CF-UC") or (http.user_agent contains "sitelock") or (http.user_agent contains "crawl" and not cf.client.bot) or (http.user_agent contains "bot" and not cf.client.bot) or (http.user_agent contains "Bot" and not cf.client.bot) or (http.user_agent contains "Crawl" and not cf.client.bot) or (http.user_agent contains "spider" and not cf.client.bot) or (http.user_agent contains "mj12bot") or (http.user_agent contains "ZoominfoBot") or (http.user_agent contains "mojeek") or (ip.src.asnum in {135061 23724 4808} and http.user_agent contains "siteaudit")',
		'action'      => 'managed_challenge',
	),
	array(
		'description' => 'MC VPNs and WP Login',
		'expression'  => '(ip.src.asnum in {60068 9009 16247 51332 212238 131199 22298 29761 62639 206150 210277 46562 8100 3214 206092 206074 206164 213074}) or (http.request.uri.path contains "wp-login")',
		'action'      => 'managed_challenge',
	),
	array(
		'description' => 'Block Web Hosts / WP Paths / TOR',
		'expression'  => '(ip.src.asnum in {26496 31815 18450 398101 50673 7393 14061 205544 199610 21501 16125 51540 264649 39020 30083 35540 55293 36943 32244 6724 63949 7203 201924 30633 208046 36352 25264 32475 23033 32475 212047 32475 31898 210920 211252 16276 23470 136907 12876 210558 132203 61317 212238 37963 13238 2639 20473 63018 395954 19437 207990 27411 53667 27176 396507 206575 20454 51167 60781 62240 398493 206092 63023 213230 26347 20738 45102 24940 57523 8100 8560 6939 14178 46606 197540 397630 9009 11878}) or (http.request.uri.path contains "xmlrpc") or (http.request.uri.path contains "wp-config") or (http.request.uri.path contains "wlwmanifest") or (cf.verified_bot_category in {"AI Crawler" "Other"}) or (ip.src.country in {"T1"})',
		'action'      => 'block',
	),
);

$rulesets = array(
	'squarecandy_rules'           => array(
		'description' => 'Square Candy FREE Rules 2025-04',
		'rules'       => $squarecandy_rules_free,
	),
	'squarecandy_rules_ecommerce' => array(
		'description' => 'Square Candy FREE Ecommerce Rules 2025-04',
		'rules'       => $squarecandy_rules_free,
	),
	'squarecandy_rules_pro'       => array(
		'description' => 'Square Candy PRO Rules 2025-04',
		'rules'       => $squarecandy_rules_pro,
	),
	'presswizards_rules'          => array(
		'description' => 'Press Wizards Rules 2025-04',
		'rules'       => $presswizards_rules,
	),
);
