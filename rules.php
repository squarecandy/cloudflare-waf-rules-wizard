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
// Allow Cloudflare Observatory
$observatory = '(http.user_agent contains "CloudflareObservatory")';
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
	$observatory,
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
	'4134', // CHINANET-BACKBONE (probably AI/TikTok) - abuse on mdhistory.org 2025/01
	'4837', // CHINANET-BACKBONE (probably AI/TikTok) - abuse on mdhistory.org 2025/01
	// '4808', // China Telecom
);
$cloud_asns = implode( ' ', $cloud_asns );

// Challenge/Block Web Hosting ASNs
$web_hosts = array(
	'26496', // GoDaddy
	'398101', // GoDaddy
	'18450', // WebNX
	'50673', // Serverius Holding B.V. (Netherlands)
	'7393', // Cybercon
	'14061', // DigitalOcean
	'31815', // GoDaddy (additional ASN)
	'205544', // Leaseweb UK Limited
	'199610', // marbis GmbH (German hosting)
	'21501', // Host Europe GmbH
	'16125', // UAB Cherry Servers (Lithuania)
	'51540', // DAL Bilgi Teknolojileri (Turkish hosting)
	'264649', // NUT HOST SRL (Argentina)
	'39020', // Comvive (Spain)
	'30083', // velia.net
	'35540', // OVH SAS
	'55293', // A2 Hosting
	'36943', // Web Africa
	'32244', // Liquid Web Inc.
	'6724', // STRATO AG (Germany)
	'63949', // Akamai Connected Cloud / Linode
	'7203', // Leaseweb USA
	'201924', // ENAHOST s.r.o. (Czech Republic)
	'30633', // Leaseweb USA
	'208046', // KOGLER Gabin (France)
	'36352', // Colocrossing
	'25264', // Afagh Andish Dadeh Pardis Co. (Iran)
	'32475', // HorizonIQ / Internap
	'23033', // Wowrack
	'212047', // Civo LTD (UK cloud)
	'31898', // Oracle Cloud
	'210920', // Civo LTD (UK cloud)
	'211252', // Akari Networks K.K. (Japan)
	'16276', // OVH SAS
	'23470', // ReliableSite.Net LLC
	'136907', // Huawei Cloud
	'12876', // Scaleway S.A.S. (France)
	'210558', // 1337 Services GmbH
	'132203', // Tencent Cloud
	'61317', // Hivelocity Inc.
	'212238', // Datacamp Limited
	'37963', // Alibaba Cloud
	'13238', // Yandex LLC
	'2639', // Zoho Corp
	'20473', // The Constant Company / Vultr
	'63018', // Dedicated.com
	'395954', // LeaseWeb USA (Los Angeles)
	'19437', // Secured Servers LLC
	'207990', // HostRoyale Technologies Pvt Ltd (India)
	'27411', // LeaseWeb USA (Chicago)
	'53667', // Frantech Solutions / BuyVM
	'27176', // DataWagon
	'396507', // Emerald Onion (Tor privacy network)
	'206575', // DATABOX d.o.o. (Bosnia)
	'20454', // Secured Servers LLC
	'51167', // Contabo GmbH
	'60781', // LeaseWeb Netherlands B.V.
	'62240', // Clouvider
	'398493', // System In Place
	'213230', // Hetzner Online GmbH
	'26347', // New Dream Network / DreamHost
	'20738', // Heart Internet (UK)
	'45102', // Alibaba (China) Technology Co., Ltd.
	'24940', // Hetzner Online GmbH
	'57523', // Chang Way Technologies Co. (Hong Kong)
	'8100', // QuadraNet Inc.
	'8560', // IONOS / 1&1
	'6939', // Hurricane Electric
	'14178', // Megacable Comunicaciones (Mexico)
	'46606', // Endurance International Group / Bluehost / HostGator
	'197540', // netcup GmbH
	'397630', // Blazing SEO LLC (proxy/scraping)
	'9009', // M247 Europe SRL
	'11878', // tzulo, inc.
	'49505', // JSC Selectel (Russia)
	'401116', // Nybula LLC https://threatfox.abuse.ch/browse/tag/Nybula%20LLC/
	'11590', // Bucklog SARL (France) - abuse on mdhistory.org 2025/04
	'210006', // tutamail.com Kazakhstan origin webhost. Maybe TOR traffic source. - abuse on orionmagazine.org 2026/03
	'211590', // tutamail.com France origin webhost. Maybe TOR traffic source. - abuse on orionmagazine.org 2026/03
);
$web_hosts = implode( ' ', $web_hosts );

$challenge_asns = '(ip.src.asnum in {' . $cloud_asns . ' ' . $web_hosts . '} and not cf.client.bot and not cf.verified_bot_category in {"Search Engine Crawler" "Search Engine Optimization" "Monitoring & Analytics" "Advertising & Marketing" "Page Preview" "Academic Research" "Security" "Accessibility" "Webhooks" "Feed Fetcher" "Aggregator"})';


// block agressive crawlers
$aggressive_crawlers = array(
	'advanced email extractor',
	'ahrefsbot',
	'aiohttp',
	'amazonbot',
	'anthropic-ai', // Anthropic AI crawler
	'AwarioBot',
	'barkrowler',
	'br-crawler',
	'bytedance', // TikTok crawler
	'bytespider', // ByteDance/TikTok crawler
	'ccbot', // Common Crawl (primary LLM training data source)
	'claudebot', // Anthropic Claude crawler
	'cms spider',
	'cohere-ai', // Cohere LLM crawler
	'contactbot',
	'contentsmartz',
	'datacha0s',
	'dataforseobot',
	'dbrowse ',
	'dotbot', // Moz SEO crawler
	'ebrowse',
	'email_hunter',
	'extractorpro',
	'FacebookBot', // Speech recognition and language model training, Minimal user-facing impact
	'friendlycrawler',
	// 'gptbot', // OpenAI GPT crawler // Let CloudFlare's AI management take care of this.
	'guestbook',
	'img2dataset', // image dataset harvesting tool
	'iplexx',
	'letscrawl.com',
	'libwww-perl', // old Perl HTTP client, rarely legitimate
	'lmqueuebot',
	'Mail.RU_Bot',
	'marginalia', // search.marginalia.nu
	'meta-externalagent', // Meta/Facebook AI model training and content indexing. Stops AI training; no effect on link previews
	'mj12bot',
	'mvaclient',
	'NapBot',
	'nasa search',
	'netsystemsresearch', // aggressive "research" crawler
	'nikto', // web vulnerability scanner
	'nsauditor',
	'panscient', // agressive bot trying to index "people" data (business contacts)
	'perplexitybot',
	'petalbot',
	'production bot',
	'program shareware',
	'python-httpx', // Python HTTP client used in modern scrapers
	'scan4mail',
	'scrapy', // Python scraping framework
	'screaming frog',
	'searchbot admin@google.com',
	'SemanticScholarBot',
	'semrushbot',
	'seokicks', // German SEO crawler
	'serpstatbot', // SerpStat SEO crawler
	'seznambot',
	'shablastbot',
	'SleepBot',
	'snap.com',
	'snapbot',
	'sogou',
	'sohu agent',
	'sqlmap', // SQL injection attack tool
	'timpibot',
	'trackback',
	'trendictionbot',
	'turnitin',
	'tweetmemebot',
	'vadixbot',
	'webemailextrac',
	'webvulncrawl',
	'yandex',
	'zgrab', // Go-based attack scanner
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
// Block Drupal patterns + misc CMS probe paths
$drupal = '(starts_with(http.request.uri.path, "/sites/default/files/")) or (starts_with(http.request.uri.path, "/sites/all/")) or (starts_with(http.request.uri.path, "/node")) or (http.request.full_uri contains "civicrm") or (ends_with(http.request.uri.path, "/javascript"))';
// Block Sensitive WP Paths
$wp_path_strings = array(
	'xmlrpc',           // WordPress XML-RPC endpoint — brute force and DDoS amplification target
	'xmrlpc',           // common misspelling by bots
	'wlwmanifest',      // Windows Live Writer manifest — unused legacy endpoint
	'wp-config',        // WordPress config file containing DB credentials
	'passwd',           // /etc/passwd probe
	'/.env',            // environment file with credentials
	'network.php',      // WordPress Multisite network admin probe / dropped malware filename
	'wp-ajf.php',       // malicious file probe
	'eval-stdin.php',   // dropped malware filename
	'/tel:',            // href injection probes
	'/tel%3a',          // href injection probes (URL-encoded)
	'/mailto',          // href injection probes (/mailto: and /mailto%3a)
	'/.git',            // git repo/object exposure
	'/phpinfo',         // PHP info disclosure
	'/phpmyadmin',      // DB admin panel probe
	'readme.html',      // WordPress version fingerprinting
	'license.txt',      // WordPress version fingerprinting
	'wp-trackback.php', // dead feature, spam/attack vector
	'debug.log',        // exposed WordPress debug log
	'/.htaccess',       // Apache config probe
	'.bak',             // backup file probes
	'.sql',             // database dump exposure
);

$wp_paths = array_map(
	function ( $path ) {
		return '(http.request.uri.path contains "' . $path . '")';
	},
	$wp_path_strings
);

$wp_paths = implode( ' or ', $wp_paths );

// Block General AI Crawlers and Assistant Bots
$ai_crawlers = '(cf.verified_bot_category in {"AI Crawler" "Other" "AI Assistant"} and not http.user_agent contains "archive.org")';
// Block OpenAI (when they actually reveal their user agent!)
$open_ai = '(http.user_agent contains "openai.com") or (http.user_agent contains "ChatGPT")';

// Fake Google Chrome (spoofed UA: claims to be Chrome but missing sec-ch-ua client hint header)
// Add new versions here as they appear in the wild
$fake_chrome_versions = array(
	'Chrome/129.0.0.0',
	'Chrome/130.0.0.0',
	'Chrome/131.0.0.0',
	'Chrome/132.0.0.0',
	'Chrome/133.0.0.0',
);
$fake_chrome_ua_check = implode(
	' or ',
	array_map(
		function ( $v ) {
			return '(http.user_agent contains "' . $v . '")';
		},
		$fake_chrome_versions
	)
);

$fake_chrome = '((' . $fake_chrome_ua_check . ') and not any(http.request.headers.names[*] eq "sec-ch-ua") and not any(http.request.headers.names[*] eq "sec-fetch-site"))';

$fu_waf = '(http.request.full_uri contains "FUCKYOUWAF")';

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
		'expression'  => $wp_paths . ' or ' . $drupal . ' or ' . $ai_crawlers . ' or ' . $open_ai . ' or ' . $fu_waf,
		'action'      => 'block',
	),
	'block_crawlers'          => array(
		'description' => 'Block Aggressive Crawlers',
		'expression'  => $aggressive_crawlers,
		'action'      => 'block',
	),
	'managed_challenge_hosts' => array(
		'description' => 'Managed Challenge Web Hosts, Cloud Providers, TOR',
		'expression'  => $challenge_asns . ' or ' . $tor . ' or ' . $fake_chrome,
		'action'      => 'managed_challenge',
	),
	'login_protection'        => array(
		'description' => 'Login Protection',
		'expression'  => '(http.request.uri.path contains "wp-login.php" and not http.request.uri.query contains "action=logout" and not http.request.uri.query contains "action=postpass")',
		'action'      => 'managed_challenge',
	),
);

$squarecandy_rules_free_ecommmerce                                    = $squarecandy_rules_free;
$squarecandy_rules_free_ecommmerce['good_actors_allow']['expression'] = $allow_expression_ecommerce;

$squarecandy_rules_pro = array(
	array(
		'description'       => 'Good Actors Allow',
		'expression'        => $allow_expression_ecommerce,
		'action'            => 'skip',
		'action_parameters' => array(
			'ruleset'  => 'current',
			'phases'   => array( 'http_ratelimit', 'http_request_sbfm', 'http_request_firewall_managed' ),
			'products' => array( 'uaBlock', 'zoneLockdown', 'waf', 'rateLimit', 'bic', 'hot', 'securityLevel' ),
		),
	),
	'block_wp_paths'          => array(
		'description' => 'Block WP Paths',
		'expression'  => $wp_paths . ' or ' . $fu_waf,
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
	'fake_chrome'             => array(
		'description' => 'Managed Challenge Fake Chrome UA',
		'expression'  => $fake_chrome,
		'action'      => 'managed_challenge',
	),
);


// depreciated... keeping for reference.
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
		'description' => 'Square Candy FREE Rules 2026-03',
		'rules'       => $squarecandy_rules_free,
	),
	'squarecandy_rules_ecommerce' => array(
		'description' => 'Square Candy FREE Ecommerce Rules 2026-03',
		'rules'       => $squarecandy_rules_free_ecommmerce,
	),
	'squarecandy_rules_pro'       => array(
		'description' => 'Square Candy PRO Rules 2026-03',
		'rules'       => $squarecandy_rules_pro,
	),
);
