<?php
// @TODO move rules here

// @TODO: Move this to a separate file and include it
// @TODO: build system for maintaining different ruleset versions

$squarecandy_rules = array(
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
	'squarecandy_rules'     => array(
		'description' => 'Square Candy FREE Rules 2025-04',
		'rules'       => $squarecandy_rules,
	),
	'squarecandy_pro_rules' => array(
		'description' => 'Square Candy PRO Rules 2025-04',
		'rules'       => $squarecandy_rules,
	),
	'presswizards_rules'    => array(
		'description' => 'Press Wizards Rules 2025-04',
		'rules'       => $presswizards_rules,
	),
);
