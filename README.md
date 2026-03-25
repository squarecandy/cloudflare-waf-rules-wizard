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

It takes your Cloudflare API key, email, and account ID, and then gets all the domains in that account, and displays a checkbox list of them all, and you can choose the domains you want to add custom WAF rules to, and bulk update all the domains with one click.

## Some Important Notes
⚠️ **Please note that this system overwites the WAF rules on all domains, it will erase the existing rules and create new ones.**

⚠️ **Use at your own risk.** These rules may block certain services such as monitoring, uptime, or CDN services, so you may need to add exclusions if those services suddenly can't connect to your domain(s), using the Events log in Cloudflare showing the user agent or other data to add to the first rule that allows requests to bypass the remaining rules.

## Configure Settings

All configurations are set in config.php and should never be checked in to git. Protecting the security of your Cloudflare API keys is **extremely important**.

* `CLOUDFLARE_EMAIL` is your email is the email you log in with.
* `CLOUDFLARE_API_KEY` you can retrieve/generate your [API key here](https://dash.cloudflare.com/profile/api-tokens). The **Global API Key** will give you access to manage all domains and accounts with this tool. With great power comes great responsibility!!!
* `CLOUDFLARE_ACCOUNT_IDS` should be an array of IDs - even if you just need 1 for now. [Here are instructions](https://developers.cloudflare.com/fundamentals/setup/find-account-and-zone-ids/) for where you can find each Account ID.

## Roadmap

* Create an "Update" Mode that will only update rules found with identical descriptions, and will leave all other existing rules as they are.
* Create an intermediary "are you sure" step that displays the existing rules and asks if you want to proceed, unless they are identical or completely blank.
* Backup/Restore system: backup existing rules to a file (probably json) and allow restoring from backup files.
* Group/Label domain checkboxes with Account name above them.
* Event logging - keep a record of what domain was updated with what ruleset when.

## Cloudflare Free Tier Rule Expression Limits

### The 4096-character limit

Cloudflare's free tier enforces a **4096-character maximum** on each custom rule expression. The `$aggressive_crawlers` rule in `rules.php` is the one most likely to approach this limit as new bots are added.

### What is and isn't available on the free tier

| Feature | Free | Pro | Business/WAF Advanced |
|---|---|---|---|
| `contains` operator | ✅ | ✅ | ✅ |
| `lower()` function | ✅ | ✅ | ✅ |
| `matches` operator (regex) | ❌ | ❌ | ✅ |

**Do not use `matches` / regex** — it requires Business or WAF Advanced and will error with: `not entitled: the use of operator Matches is not allowed`.

### How the crawler list is kept under the limit

- Each entry uses `(lower(http.user_agent) contains "string")` — `lower()` makes matching case-insensitive without needing `matches`.
- Entries in the array are **all lowercase** — `lower()` handles the case conversion at match time.
- Strings are shortened where it's safe to do so (e.g. `tweetmemebot` → `tweetmeme`, `dataforseobot` → `dataforseo`, `snap.com` + `snapbot` → `snap`). Only shorten when the shorter string is still unique and won't cause false positives.
- **Never shorten to a generic word** that could match real browsers or services (e.g. do NOT shorten `google-extended` to `google` — that would block Googlebot).
- The `admin@google` entry matches `searchbot admin@google.com` spam — the shorter form is safe and unique.
- Entries with URLs in their user agent string (e.g. `gptbot` contains `openai.com/gptbot`) are safe to use the short form because `lower()` will catch both the name and the url.

### Checking the current expression length

```bash
cd /path/to/cloudflare.localhost
php -r 'include "rules.php"; echo "Length: " . strlen($aggressive_crawlers) . "\n";'
```

The target is to stay under **4096 characters**. As of the last update the expression is ~3983 chars, leaving ~113 chars of headroom.

### Adding new crawlers

When adding new entries, run the length check above after adding. If over 4096, shorten an existing entry or remove one that has low value.
