# Cloudflare WAF Rules Wizard - Standalone Version

Thanks to:

* The amazing work by Troy Glancy and his superb [Cloudflare WAF Rules](https://webagencyhero.com/cloudflare-waf-rules-v3/?utm=github-presswizards-cloudflare-waf-rules-wizard). Read through the WAF rules logic and details on his site.
* Rob Marlbrough - PressWizards.com - for the original WordPress plugin this standalone version is based on.

## Security Notes

* Only ever run this tool in a protected localhost environment, for security
* Never check in Cloudflare security credentials

## Installation and Operation

* Setup your config.php file based on config-example.php
* Maintain different rule sets in rules.php

It takes your Cloudflare API key, email, and account ID, and then gets all the domains in that account, and displays a checkbox list of them all, and you can choose the domains you want to add Troy’s WAF rules to, and bulk update all the domains with one click. Please see the notes and security tips in the plugin settings page.
 
 ## Some Important Notes
 ⚠️ **Please note that this plugin overwites the WAF rules on all domains, it will erase the existing rules and create new ones.** These 5 rules should work with Cloudflare Free, Pro and Business plans.
 
 ⚠️ **Use at your own risk.** These rules may block certain services such as monitoring, uptime, or CDN services, so you may need to add exclusions if those services suddenly can't connect to your domain(s), using the Events log in Cloudflare showing the user agent or other data to add to the first rule that allows requests to bypass the remaining rules.
 
 ## Configure Settings
 On the plugin's option page: First, add you credentials to the Cloudflare WAF Rules Wizard settings page in the plugin. Your email is the email you log in with. You can retrieve your [API key here](https://dash.cloudflare.com/profile/api-tokens). And [here are instructions](https://developers.cloudflare.com/fundamentals/setup/find-account-and-zone-ids/)  for where you can find your Account ID.
