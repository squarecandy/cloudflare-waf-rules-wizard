#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"

# Flags
DRY_RUN=0
DEPLOY_CONFIG=0
DEPLOY_KEYS=0
DEPLOY_FILTERS=0
DEPLOY_ALL=1

SERVER_SLUG=""

# Parse arguments
while [[ $# -gt 0 ]]; do
    case "$1" in
        --dry-run)
            DRY_RUN=1
            shift
            ;;
        --config)
            DEPLOY_CONFIG=1
            DEPLOY_ALL=0
            shift
            ;;
        --keys)
            DEPLOY_KEYS=1
            DEPLOY_ALL=0
            shift
            ;;
        --filters)
            DEPLOY_FILTERS=1
            DEPLOY_ALL=0
            shift
            ;;
        -h|--help)
            cat <<EOF
Usage: ./fail2ban-scripts/update-fail2ban.sh [OPTIONS] <server-slug>

DESCRIPTION:
  Deploy fail2ban integration files to a server, with optional per-component deployment.
  Without component flags, deploys everything (config, keys, filters, jails, and reloads).

POSITIONAL ARGUMENTS:
  <server-slug>          Server identifier from FAIL2BAN_SERVERS in config.php

OPTIONS:
    --dry-run             Show commands without executing them
  --config               Deploy only config file and sync script
  --keys                 Deploy only API key files
  --filters              Deploy only fail2ban filters and jails
  -h, --help             Show this help message

EXAMPLES:
  Full deployment:
    ./fail2ban-scripts/update-fail2ban.sh production

  Preview all commands:
        ./fail2ban-scripts/update-fail2ban.sh --dry-run production

  Deploy only config changes:
    ./fail2ban-scripts/update-fail2ban.sh --config production

  Deploy only filters with dry-run:
    ./fail2ban-scripts/update-fail2ban.sh --dry-run --filters production

  Combine multiple components (config + filters):
    ./fail2ban-scripts/update-fail2ban.sh --dry-run --config --filters production
EOF
            exit 0
            ;;
        *)
            if [[ "$1" == -* ]]; then
                echo "ERROR: Unknown option: $1"
                echo "Try --help for usage information."
                exit 1
            fi
            SERVER_SLUG="$1"
            shift
            ;;
    esac
done

if [[ -z "${SERVER_SLUG}" ]]; then
    echo "ERROR: Missing required argument <server-slug>"
    echo "Try --help for usage information."
    exit 1
fi

# Helper function to run commands (dry-run aware)
run_cmd() {
    local desc="$1"
    shift
    local cmd=("$@")
    
    if [[ $DRY_RUN -eq 1 ]]; then
        echo "[DRY-RUN] ${desc}"
        echo "          ${cmd[@]}"
    else
        echo "${desc}"
        "${cmd[@]}"
    fi
}

# Helper function to run ssh commands
run_ssh() {
    local desc="$1"
    local user_host="$2"
    local cmd="$3"
    
    if [[ $DRY_RUN -eq 1 ]]; then
        echo "[DRY-RUN] ${desc}"
        echo "          ssh ${user_host} \"${cmd}\""
    else
        echo "${desc}"
        ssh "${user_host}" "${cmd}"
    fi
}

# Helper function to run scp commands
run_scp() {
    local desc="$1"
    shift
    local args=("$@")
    local dest_index=$(( ${#args[@]} - 1 ))
    local dest="${args[$dest_index]}"
    
    if [[ $DRY_RUN -eq 1 ]]; then
        echo "[DRY-RUN] ${desc}"
        echo "          scp ${args[*]}"
    else
        echo "${desc}"
        scp "${args[@]}"
    fi
}

get_config_path() {
    printf 'fail2ban-scripts/generated/cloudflare-fail2ban-config-%s.txt\n' "$1"
}

remote_stage_dir() {
    printf '/tmp/cloudflare-fail2ban-%s\n' "$1"
}

cd "${PROJECT_ROOT}"

# Resolve server metadata
SERVER_META=$(php -r '
require "config.php";
$slug = $argv[1];
$servers = defined("FAIL2BAN_SERVERS") ? FAIL2BAN_SERVERS : array();
if (!isset($servers[$slug])) {
    fwrite(STDERR, "Unknown server slug: {$slug}\n");
    exit(1);
}
$server = $servers[$slug];
$ssh_user = isset($server["ssh_user"]) ? $server["ssh_user"] : "root";
$hostname = isset($server["hostname"]) ? $server["hostname"] : "";
if ("" === $hostname) {
    fwrite(STDERR, "Server {$slug} is missing hostname.\n");
    exit(1);
}
echo $ssh_user . ":" . $hostname;
' "${SERVER_SLUG}")

SSH_USER="${SERVER_META%:*}"
HOSTNAME="${SERVER_META#*:}"

if [[ -z "${SSH_USER}" || -z "${HOSTNAME}" ]]; then
    echo "ERROR: Failed to resolve server SSH settings for '${SERVER_SLUG}'."
    exit 1
fi

SSH_CONN="${SSH_USER}@${HOSTNAME}"
REMOTE_STAGE_DIR="$(remote_stage_dir "${SERVER_SLUG}")"

# Resolve token files into array
TOKEN_FILES=()
while IFS= read -r token_file; do
    if [[ -n "${token_file}" ]]; then
        TOKEN_FILES+=("${token_file}")
    fi
done < <(php -r '
require "config.php";
require "functions.php";
$slug = $argv[1];
$accounts = defined("CLOUDFLARE_ACCOUNTS") ? CLOUDFLARE_ACCOUNTS : array();
$token_path = defined("FAIL2BAN_TOKEN_PATH") ? rtrim(FAIL2BAN_TOKEN_PATH, "/") : "";
if ("" === $token_path) {
    exit(0);
}
foreach ($accounts as $idx => $account) {
    if (isset($account["servers"]) && !in_array($slug, $account["servers"], true)) {
        continue;
    }
    echo $token_path . "/cloudflare-api-key-" . pw_get_account_slug($idx) . PHP_EOL;
}
' "${SERVER_SLUG}")

# Show mode indicator
if [[ $DRY_RUN -eq 1 ]]; then
    echo "=== DRY-RUN MODE (no changes will be made) ==="
fi
echo "Server: ${SERVER_SLUG} (${SSH_CONN})"
echo "Remote staging dir: ${REMOTE_STAGE_DIR}"
echo ""

# Full deployment (default)
if [[ $DEPLOY_ALL -eq 1 ]]; then
    echo "=== Full Deployment ==="
    
    if [[ $DRY_RUN -eq 1 ]]; then
        echo "[1/7] Would generate fail2ban config for server '${SERVER_SLUG}'..."
        CONFIG_PATH="$(get_config_path "${SERVER_SLUG}")"
    else
        echo "[1/7] Generating fail2ban config for server '${SERVER_SLUG}'..."
        CONFIG_PATH="$(php fail2ban-scripts/generate-fail2ban-config.php "${SERVER_SLUG}")"
    fi
    
    if [[ -z "${CONFIG_PATH}" ]]; then
        echo "ERROR: Failed to generate config path."
        exit 1
    fi
    echo "       Generated: ${CONFIG_PATH}"
    echo ""
    
    run_cmd "[2/7] Regenerating fail2ban filters..." \
        php generate-fail2ban-filters.php
    echo ""
    
    run_ssh "[3/7] Ensuring remote directories exist..." \
        "${SSH_CONN}" \
        "mkdir -p ${REMOTE_STAGE_DIR} && chmod 700 ${REMOTE_STAGE_DIR} && sudo mkdir -p /usr/local/bin/cloudflare-fail2ban /root/.cloudflare /etc/fail2ban/filter.d /etc/fail2ban/jail.d && sudo chmod 700 /root/.cloudflare"
    echo ""
    
    run_scp "[4/7] Uploading config and sync script to staging..." \
        "${CONFIG_PATH}" \
        "fail2ban-scripts/cloudflare-fail2ban-sync" \
        "${SSH_CONN}:${REMOTE_STAGE_DIR}/"
    run_ssh "       Installing config and sync script..." \
        "${SSH_CONN}" \
        "sudo install -m 0644 ${REMOTE_STAGE_DIR}/$(basename "${CONFIG_PATH}") /usr/local/bin/cloudflare-fail2ban/cloudflare-fail2ban-config && sudo install -m 0755 ${REMOTE_STAGE_DIR}/cloudflare-fail2ban-sync /usr/local/bin/cloudflare-fail2ban/cloudflare-fail2ban-sync"
    echo ""
    
    if [[ ${#TOKEN_FILES[@]} -gt 0 ]]; then
        echo "[5/7] Uploading token files to staging..."
        token_basenames=()
        for token_file in "${TOKEN_FILES[@]}"; do
            if [[ -f "${token_file}" ]]; then
                token_basenames+=("$(basename "${token_file}")")
                run_scp "       " "${token_file}" "${SSH_CONN}:${REMOTE_STAGE_DIR}/"
            else
                echo "WARN: Missing token file: ${token_file}"
            fi
        done

        if [[ ${#token_basenames[@]} -gt 0 ]]; then
            run_ssh "       Installing token files..." \
                "${SSH_CONN}" \
                "sudo install -m 0600 ${REMOTE_STAGE_DIR}/cloudflare-api-key-* /root/.cloudflare/"
        fi
    else
        echo "[5/7] No token files configured via FAIL2BAN_TOKEN_PATH."
    fi
    echo ""
    
    run_scp "[6/7] Uploading fail2ban filters and jails to staging..." \
        fail2ban-filters/sqcdy-*.conf fail2ban-filters/sqcdy-*.local fail2ban-jails/sqcdy-jails.conf \
        "${SSH_CONN}:${REMOTE_STAGE_DIR}/"
    run_ssh "       Installing filters and jails..." \
        "${SSH_CONN}" \
        "sudo install -m 0644 ${REMOTE_STAGE_DIR}/sqcdy-*.conf ${REMOTE_STAGE_DIR}/sqcdy-*.local /etc/fail2ban/filter.d/ && sudo install -m 0644 ${REMOTE_STAGE_DIR}/sqcdy-jails.conf /etc/fail2ban/jail.d/sqcdy-jails.conf"
    echo ""
    
    run_ssh "[7/7] Reloading fail2ban, syncing, and cleaning up..." \
        "${SSH_CONN}" \
        "sudo ln -sf /usr/local/bin/cloudflare-fail2ban/cloudflare-fail2ban-sync /usr/local/bin/cloudflare-fail2ban-sync && sudo fail2ban-client reload && sudo /usr/local/bin/cloudflare-fail2ban/cloudflare-fail2ban-sync && rm -rf ${REMOTE_STAGE_DIR}"
    echo ""
    
else
    # Component-based deployment
    echo "=== Component Deployment ==="
    
    if [[ $DEPLOY_CONFIG -eq 1 ]]; then
        if [[ $DRY_RUN -eq 1 ]]; then
            echo "Would generate fail2ban config for server '${SERVER_SLUG}'..."
            CONFIG_PATH="$(get_config_path "${SERVER_SLUG}")"
        else
            echo "Generating fail2ban config for server '${SERVER_SLUG}'..."
            CONFIG_PATH="$(php fail2ban-scripts/generate-fail2ban-config.php "${SERVER_SLUG}")"
        fi
        
        if [[ -z "${CONFIG_PATH}" ]]; then
            echo "ERROR: Failed to generate config path."
            exit 1
        fi
        echo "Generated: ${CONFIG_PATH}"
        echo ""
        
        run_ssh "Ensuring remote directories..." \
            "${SSH_CONN}" \
            "mkdir -p ${REMOTE_STAGE_DIR} && chmod 700 ${REMOTE_STAGE_DIR} && sudo mkdir -p /usr/local/bin/cloudflare-fail2ban"
        
        run_scp "Uploading config and sync script to staging..." \
            "${CONFIG_PATH}" \
            "fail2ban-scripts/cloudflare-fail2ban-sync" \
            "${SSH_CONN}:${REMOTE_STAGE_DIR}/"
        
        run_ssh "Installing config and sync script..." \
            "${SSH_CONN}" \
            "sudo install -m 0644 ${REMOTE_STAGE_DIR}/$(basename "${CONFIG_PATH}") /usr/local/bin/cloudflare-fail2ban/cloudflare-fail2ban-config && sudo install -m 0755 ${REMOTE_STAGE_DIR}/cloudflare-fail2ban-sync /usr/local/bin/cloudflare-fail2ban/cloudflare-fail2ban-sync && sudo ln -sf /usr/local/bin/cloudflare-fail2ban/cloudflare-fail2ban-sync /usr/local/bin/cloudflare-fail2ban-sync && rm -rf ${REMOTE_STAGE_DIR}"
        echo ""
    fi
    
    if [[ $DEPLOY_KEYS -eq 1 ]]; then
        if [[ ${#TOKEN_FILES[@]} -gt 0 ]]; then
            echo "Uploading token files to staging..."
            run_ssh "Ensuring key directory..." \
                "${SSH_CONN}" \
                "mkdir -p ${REMOTE_STAGE_DIR} && chmod 700 ${REMOTE_STAGE_DIR} && sudo mkdir -p /root/.cloudflare && sudo chmod 700 /root/.cloudflare"
            
            token_basenames=()
            for token_file in "${TOKEN_FILES[@]}"; do
                if [[ -f "${token_file}" ]]; then
                    token_basenames+=("$(basename "${token_file}")")
                    run_scp "Deploying $(basename "${token_file}")..." \
                        "${token_file}" \
                        "${SSH_CONN}:${REMOTE_STAGE_DIR}/"
                else
                    echo "WARN: Missing token file: ${token_file}"
                fi
            done

            if [[ ${#token_basenames[@]} -gt 0 ]]; then
                run_ssh "Installing token files..." \
                    "${SSH_CONN}" \
                    "sudo install -m 0600 ${REMOTE_STAGE_DIR}/cloudflare-api-key-* /root/.cloudflare/ && rm -rf ${REMOTE_STAGE_DIR}"
            fi
        else
            echo "No token files configured via FAIL2BAN_TOKEN_PATH."
        fi
        echo ""
    fi
    
    if [[ $DEPLOY_FILTERS -eq 1 ]]; then
        run_cmd "Regenerating fail2ban filters..." php generate-fail2ban-filters.php
        
        run_ssh "Ensuring filter staging directories..." \
            "${SSH_CONN}" \
            "mkdir -p ${REMOTE_STAGE_DIR} && chmod 700 ${REMOTE_STAGE_DIR} && sudo mkdir -p /etc/fail2ban/filter.d /etc/fail2ban/jail.d"

        run_scp "Uploading filters and jails to staging..." \
            fail2ban-filters/sqcdy-*.conf fail2ban-filters/sqcdy-*.local fail2ban-jails/sqcdy-jails.conf \
            "${SSH_CONN}:${REMOTE_STAGE_DIR}/"
        
        run_ssh "Installing filters and jails and reloading fail2ban..." \
            "${SSH_CONN}" \
            "sudo install -m 0644 ${REMOTE_STAGE_DIR}/sqcdy-*.conf ${REMOTE_STAGE_DIR}/sqcdy-*.local /etc/fail2ban/filter.d/ && sudo install -m 0644 ${REMOTE_STAGE_DIR}/sqcdy-jails.conf /etc/fail2ban/jail.d/sqcdy-jails.conf && sudo fail2ban-client reload && rm -rf ${REMOTE_STAGE_DIR}"
        echo ""
    fi
fi

if [[ $DRY_RUN -eq 1 ]]; then
    echo "=== DRY-RUN COMPLETE (no commands were executed) ==="
else
    echo "Done."
fi
