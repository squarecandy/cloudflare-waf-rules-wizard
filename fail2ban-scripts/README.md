# Cloudflare + Fail2ban Integration

Automatically sync fail2ban banned IPs to Cloudflare IP Block Lists for network-level protection.

## Features

- **Instant blocking**: When fail2ban bans an IP, it's immediately added to Cloudflare
- **Instant unbanning**: When fail2ban unbans an IP, it's immediately removed from Cloudflare
- **Optional periodic sync**: Run a sync script as a safety net to ensure consistency (recommended but not required)
- **Multi-account support**: Supports multiple Cloudflare accounts on one server (for hosting sites across different CF accounts)
- **Detailed tracking**: Each blocked IP includes the date, jail name, and server that blocked it

## Prerequisites

- Ubuntu server with fail2ban installed and configured
- curl installed (`sudo apt install curl`)
- One or more Cloudflare accounts with API access
- Cloudflare IP List created in each account (with the same List ID)

## Setup Instructions

### 1. Create Cloudflare IP List

**Important**: If you have multiple Cloudflare accounts on this server, create a list with the **same List ID** in each account. Use `cf_fail2ban_blocked` for the list ID unless you have a specific reason to use a different ID.

1. Log into your Cloudflare account(s)
2. Go to **Manage Account** → **Configurations** → **Lists**
3. Click **Create list**
4. Name it (e.g., "Fail2ban Blocked IPs")
5. Set type to **IP List**
6. Note the **List ID** (you'll need this for configuration)
7. If you have multiple accounts, repeat steps 1-5 in each account, ensuring the List ID matches

### 2. Get Cloudflare API Credentials

**For each Cloudflare account**:

1. In Cloudflare, go to **My Profile** → **API Tokens**
2. Create a token with these permissions:
   - **Account** → **Account Filter Lists** → **Edit**
3. Note your **Account ID** (found in the URL or under any domain's overview)
4. Save the API token for this account

### 3. Install Scripts on Server

```bash
# Create directory for scripts
sudo mkdir -p /usr/local/bin/cloudflare-fail2ban
cd /usr/local/bin/cloudflare-fail2ban

# Copy the scripts (adjust source path as needed)
sudo cp /path/to/cloudflare-fail2ban-block .
sudo cp /path/to/cloudflare-fail2ban-unban .
sudo cp /path/to/cloudflare-fail2ban-sync .
sudo cp cloudflare-fail2ban-config.example cloudflare-fail2ban-config

# Make scripts executable
sudo chmod +x cloudflare-fail2ban-block
sudo chmod +x cloudflare-fail2ban-unban
sudo chmod +x cloudflare-fail2ban-sync

# Create symbolic links in /usr/local/bin for easier access
sudo ln -s /usr/local/bin/cloudflare-fail2ban/cloudflare-fail2ban-block /usr/local/bin/cloudflare-fail2ban-block
sudo ln -s /usr/local/bin/cloudflare-fail2ban/cloudflare-fail2ban-unban /usr/local/bin/cloudflare-fail2ban-unban
sudo ln -s /usr/local/bin/cloudflare-fail2ban/cloudflare-fail2ban-sync /usr/local/bin/cloudflare-fail2ban-sync
```

### 4. Configure API Keys

**For each Cloudflare account**, create a separate API key file:

```bash
# Create cloudflare directory in root's home
sudo mkdir -p ~/.cloudflare

# Create API key file for first account
sudo nano ~/.cloudflare/cloudflare-api-key-account1
# Paste your first Cloudflare API token and save

# If you have multiple accounts, create additional files
sudo nano ~/.cloudflare/cloudflare-api-key-account2
# Paste your second Cloudflare API token and save

# Secure the files
sudo chmod 600 ~/.cloudflare/cloudflare-api-key-*
```

### 5. Configure the Integration

Edit the configuration file:

```bash
sudo nano /usr/local/bin/cloudflare-fail2ban/cloudflare-fail2ban-config
```

Fill in your values:

```bash
# The List ID (same across all your accounts)
CLOUDFLARE_LIST_ID="your_list_id_here"

# Array of account IDs (add one per Cloudflare account)
CLOUDFLARE_ACCOUNT_IDS=(
    "abcd123456abcd123456abcd12345678"
    "bbcd123456abcd123456abcd12345679"
)

# Array of API key file paths (must match the order of account IDs above)
CLOUDFLARE_API_KEY_FILES=(
    "$HOME/.cloudflare/cloudflare-api-key-account1"
    "$HOME/.cloudflare/cloudflare-api-key-account2"  # Remove if you only have one account
)

SERVER_NAME="$(hostname)"  # Or customize this
```

**Single Account Example:**
```bash
CLOUDFLARE_LIST_ID="abc123def456"
CLOUDFLARE_ACCOUNT_IDS=("your_account_id")
CLOUDFLARE_API_KEY_FILES=("$HOME/.cloudflare/cloudflare-api-key-account1")
```

### 6. Install Fail2ban Action

```bash
# Copy the action configuration
sudo cp cloudflare-block.conf /etc/fail2ban/action.d/

# Edit to verify the script path is correct
sudo nano /etc/fail2ban/action.d/cloudflare-block.conf
```

### 7. Enable Action in Fail2ban Jails

Edit your jail configuration:

```bash
sudo nano /etc/fail2ban/jail.local
```

Add the cloudflare-block action to your jails. For example:

```ini
[sshd]
enabled = true
port = ssh
filter = sshd
logpath = /var/log/auth.log
maxretry = 5
bantime = 3600
# Add cloudflare-block to the action list
action = %(action_)s
         cloudflare-block

[nginx-limit-req]
enabled = true
filter = nginx-limit-req
logpath = /var/log/nginx/error.log
maxretry = 10
# Add cloudflare-block here too
action = %(action_)s
         cloudflare-block
```

Or apply it globally in the `[DEFAULT]` section:

```ini
[DEFAULT]
# Add cloudflare-block to default action
banaction = iptables-multiport
            cloudflare-block
```

### 8. Setup Periodic Sync (Optional but Recommended)

The integration now uses `actionunban` to immediately remove IPs when fail2ban unbans them. However, it's recommended to run a periodic sync as a safety net to handle edge cases like:
- Fail2ban service restarts
- Manual IP unbans via fail2ban-client
- Network failures during ban/unban operations
- Server reboots

Add a cron job to sync every 2-4 hours:

```bash
sudo crontab -e
```

Add this line (runs every 2 hours):

```cron
0 */2 * * * /usr/local/bin/cloudflare-fail2ban-sync >> /var/log/cloudflare-fail2ban-sync.log 2>&1
```

Or for every 4 hours:

```cron
0 */4 * * * /usr/local/bin/cloudflare-fail2ban-sync >> /var/log/cloudflare-fail2ban-sync.log 2>&1
```

### 9. Restart Fail2ban

```bash
sudo systemctl restart fail2ban
```

## Usage

### How It Works

1. **Ban**: When fail2ban bans an IP, it immediately calls `cloudflare-fail2ban-block` to add it to Cloudflare
2. **Unban**: When fail2ban unbans an IP (after bantime expires), it immediately calls `cloudflare-fail2ban-unban` to remove it from Cloudflare
3. **Sync** (optional): Periodic sync ensures Cloudflare matches fail2ban's state, catching any missed updates

### Test the Instant Block

Trigger a fail2ban ban (e.g., failed SSH attempts) and check:

```bash
# Check fail2ban status
sudo fail2ban-client status sshd

# Check syslog for cloudflare entries
sudo tail -f /var/log/syslog | grep cloudflare
```

### Manual Sync

Run the sync script manually:

```bash
sudo /usr/local/bin/cloudflare-fail2ban-sync
```

### View Sync Logs

```bash
sudo tail -f /var/log/cloudflare-fail2ban-sync.log
```

## Cloudflare WAF Rule Setup

After setting up the integration, create a WAF rule to block the IPs:

1. Go to **Security** → **WAF** → **Custom rules**
2. Click **Create rule**
3. Name it (e.g., "Block Fail2ban IPs")
4. Set the expression:
   - Field: **IP Source Address**
   - Operator: **is in list**
   - Value: Select your "Fail2ban Blocked IPs" list
5. Action: **Block**
6. Deploy

## Troubleshooting

### Check if scripts are working

```bash
# Test the block script
sudo /usr/local/bin/cloudflare-fail2ban-block 1.2.3.4 test-jail

# Test the unban script
sudo /usr/local/bin/cloudflare-fail2ban-unban 1.2.3.4 test-jail

# Test the sync script
sudo /usr/local/bin/cloudflare-fail2ban-sync
```

### Check fail2ban logs

```bash
sudo tail -f /var/log/fail2ban.log
```

### Verify Cloudflare API access

```bash
# Load API key for first account
API_KEY=$(sudo cat ~/.cloudflare/cloudflare-api-key-account1)

# Test API call (replace ACCOUNT_ID and LIST_ID)
curl -X GET "https://api.cloudflare.com/client/v4/accounts/ACCOUNT_ID/rules/lists/LIST_ID/items" \
  -H "Authorization: Bearer $API_KEY" \
  -H "Content-Type: application/json"
```

## Architecture

```
┌─────────────┐
│  Fail2ban   │
└──────┬──────┘
       │ Detects malicious IP
       ├──► Local iptables ban
       │
       ├──► cloudflare-fail2ban-block
       │    (instant add to Cloudflare)
       │
       └──► cloudflare-fail2ban-unban
            (instant remove when unbanned)
                   │
                   ▼
            ┌──────────────┐
            │  Cloudflare  │
            │   IP List    │◄──── cloudflare-fail2ban-sync
            └──────────────┘      (optional periodic safety sync)
                   │              - Runs every 2-4 hours
                   │              - Ensures consistency
                   │              - Handles edge cases
                   ▼
            ┌──────────────┐
            │ Cloudflare   │
            │  WAF Rule    │
            │  (blocks)    │
            └──────────────┘
```

## File Overview

- **cloudflare-fail2ban-block**: Script called by fail2ban when IP is banned (instant add)
- **cloudflare-fail2ban-unban**: Script called by fail2ban when IP is unbanned (instant remove)
- **cloudflare-fail2ban-sync**: Optional cron script that syncs all banned IPs (safety net)
- **cloudflare-fail2ban-config**: Configuration file (site-specific, not checked in)
- **cloudflare-fail2ban-config.example**: Example configuration template
- **cloudflare-block.conf**: Fail2ban action configuration file

## Security Notes

- The config file (`cloudflare-fail2ban-config`) should not be committed to git
- API key files should have restricted permissions (600)
- Scripts should be owned by root and only writable by root
