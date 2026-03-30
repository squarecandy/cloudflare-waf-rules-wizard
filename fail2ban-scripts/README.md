# Cloudflare + Fail2ban Integration

Automatically sync fail2ban banned IPs to Cloudflare IP Block Lists for network-level protection.

## Architecture

Uses a **cron-based sync** approach: every 5 minutes, `cloudflare-fail2ban-sync` reads all currently banned IPs from fail2ban and replaces the Cloudflare IP list via a single PUT request per account.

Why sync-only instead of instant ban/unban?

- **Rate limit safety**: At high attack volume (100+ bans/hour across many accounts), individual ban events could exhaust Cloudflare's 1200 req/300s API rate limit during bursts. One PUT per account per sync means 1 call per accounts every 5 minutes regardless of ban volume.
- **Idempotent**: Works correctly after fail2ban restarts, server reboots, and manual unbans.
- **IPv4 required**: Linode/Akamai IPv6 ranges are aggressively rate-limited by Cloudflare's API. All curl calls use `-4` to force IPv4.

## Features

- **5-minute sync**: Banned IPs appear in Cloudflare within 5 minutes
- **Automatic cleanup**: When fail2ban unbans an IP, the next sync removes it from Cloudflare
- **Multi-account support**: Syncs to multiple Cloudflare accounts on one server
- **Atomic replacement**: Uses PUT /items to replace the entire list in one API call per account

## Prerequisites

- Linux server with fail2ban installed and configured
- `curl` and `jq` installed (`sudo apt install curl jq` or `sudo yum install curl jq`)
- One or more Cloudflare accounts with API access
- Cloudflare IP List created in each account (same List ID across all accounts)

## Setup

### 1. Create Cloudflare IP List

**Important**: If you have multiple Cloudflare accounts, create a list with the **same List ID** in each account. Use `cf_fail2ban_blocked` unless you have a specific reason to use a different ID.

1. Log into your Cloudflare account(s)
2. Go to **Manage Account** → **Configurations** → **Lists**
3. Click **Create list**, name it (e.g., "Fail2ban Blocked IPs"), set type to **IP List**
4. If you have multiple accounts, repeat in each account using the same List ID

### 2. Get Cloudflare API Credentials

For each Cloudflare account:

1. Go to **My Profile** → **API Tokens** → **Create Token**
2. Permissions: **Account** → **Account Filter Lists** → **Edit**
3. Note your **Account ID** (found in any domain's overview sidebar)
4. Save the API token

### 3. Install Scripts on Server

```bash
# Create directory
sudo mkdir -p /usr/local/bin/cloudflare-fail2ban

# Copy the sync script (run from project root on your Mac)
scp fail2ban-scripts/cloudflare-fail2ban-sync user@your-server:/usr/local/bin/cloudflare-fail2ban/
ssh user@your-server "sudo chmod +x /usr/local/bin/cloudflare-fail2ban/cloudflare-fail2ban-sync"

# Optional symlink for convenience
sudo ln -sf /usr/local/bin/cloudflare-fail2ban/cloudflare-fail2ban-sync /usr/local/bin/cloudflare-fail2ban-sync
```

### 4. Configure API Keys

```bash
# Create directory for API key files
sudo mkdir -p /root/.cloudflare
sudo chmod 700 /root/.cloudflare

# Create one file per Cloudflare account
sudo nano /root/.cloudflare/cloudflare-api-key-account1
# Paste the API token and save

# Secure the files
sudo chmod 600 /root/.cloudflare/cloudflare-api-key-*
```

### 5. Configure the Integration

```bash
sudo cp /usr/local/bin/cloudflare-fail2ban/cloudflare-fail2ban-config.example \
        /usr/local/bin/cloudflare-fail2ban/cloudflare-fail2ban-config
sudo nano /usr/local/bin/cloudflare-fail2ban/cloudflare-fail2ban-config
```

Fill in your values — see `cloudflare-fail2ban-config.example` for the full format.

### 6. Set Up the Sync Cron

The sync cron is the primary mechanism — no changes to `jail.local` are needed.

```bash
echo '*/5 * * * * root /usr/local/bin/cloudflare-fail2ban/cloudflare-fail2ban-sync >> /var/log/cloudflare-fail2ban-sync.log 2>&1' \
  | sudo tee /etc/cron.d/cloudflare-fail2ban-sync
```

### 7. Verify

Run the sync manually to confirm everything is working:

```bash
sudo /usr/local/bin/cloudflare-fail2ban/cloudflare-fail2ban-sync
sudo tail -50 /var/log/cloudflare-fail2ban-sync.log
```

## Usage

### Manual Sync

```bash
sudo /usr/local/bin/cloudflare-fail2ban/cloudflare-fail2ban-sync
```

### View Sync Logs

```bash
sudo tail -f /var/log/cloudflare-fail2ban-sync.log
```

## Cloudflare WAF Rule

After setup, create a WAF rule to block the IPs:

1. Go to **Security** → **WAF** → **Custom rules** → **Create rule**
2. Expression: IP Source Address **is in list** → select your fail2ban list
3. Action: **Block**
4. Deploy

If you use the `cloudflare.localhost` management tool, the WAF block rule is automatically included in the **Block WP Paths** ruleset — just update rules via the WAF Rules Manager tab.

## Architecture Diagram

```
┌─────────────┐
│  Fail2ban   │
└──────┬──────┘
       │ Detects malicious IP
       └──► Local iptables ban

                   (every 5 min via cron)
              cloudflare-fail2ban-sync
                   │
                   ├── Reads all banned IPs from fail2ban
                   ├── Builds a single JSON payload
                   └── PUT /items → replaces list (1 API call/account)
                              │
                              ▼
                   ┌──────────────────┐
                   │  Cloudflare      │
                   │  IP List         │
                   └──────────────────┘
                              │
                              ▼
                   ┌──────────────────┐
                   │  Cloudflare      │
                   │  WAF Rule        │
                   │  (blocks IPs)    │
                   └──────────────────┘
```

## Files

- **cloudflare-fail2ban-sync**: Cron script — reads fail2ban state and replaces Cloudflare IP lists
- **cloudflare-fail2ban-config**: Configuration file (site-specific, not checked in to git)
- **cloudflare-fail2ban-config.example**: Example configuration template

## Troubleshooting

### Test Cloudflare API access

```bash
API_KEY=$(sudo cat /root/.cloudflare/cloudflare-api-key-account1)

# Use -4 to force IPv4 — Linode/Akamai IPv6 ranges may be rate-limited by Cloudflare
curl -4 -s -o /dev/null -w "%{http_code}" \
  -H "Authorization: Bearer $API_KEY" \
  "https://api.cloudflare.com/client/v4/accounts/YOUR_ACCOUNT_ID/rules/lists"
```

### Check fail2ban status

```bash
sudo fail2ban-client status
sudo tail -f /var/log/fail2ban.log
```

### Check sync logs

```bash
sudo tail -f /var/log/cloudflare-fail2ban-sync.log
```

## Security Notes

- The config file (`cloudflare-fail2ban-config`) should not be committed to git — it's in `.gitignore`
- API key files should have restricted permissions (`chmod 600`)
- API tokens use minimal scope: Account Filter Lists: Edit only

