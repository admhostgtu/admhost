#!/bin/bash
# =============================================================================
# Provisionnement complet : Linux user + SSH + mailbox.
# Usage : bash provision_full.sh user_id=1 service_id=5 username=john email=john@example.com
# Sortie  : JSON sur stdout avec toutes les credentials
# =============================================================================
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"

for arg in "$@"; do
    key="${arg%%=*}"
    val="${arg#*=}"
    case "$key" in
        user_id)    USER_ID="$val" ;;
        service_id) SERVICE_ID="$val" ;;
        username)   USERNAME="$val" ;;
        email)      EMAIL="$val" ;;
    esac
done

if [[ -z "${USERNAME:-}" || -z "${EMAIL:-}" ]]; then
    echo '{"success":false,"error":"username et email requis"}'
    exit 1
fi

# Étape 1 : Utilisateur Linux
LINUX_RESULT=$(bash "$SCRIPT_DIR/create_linux_user.sh" "username=${USERNAME}" "user_id=${USER_ID:-0}")
LINUX_OK=$(echo "$LINUX_RESULT" | grep -o '"success":[^,]*' | cut -d: -f2)

if [[ "$LINUX_OK" != "true" ]]; then
    echo "$LINUX_RESULT"
    exit 1
fi

# Étape 2 : Accès SSH
SSH_RESULT=$(bash "$SCRIPT_DIR/create_ssh_access.sh" "username=${USERNAME}")
SSH_OK=$(echo "$SSH_RESULT" | grep -o '"success":[^,]*' | cut -d: -f2)

if [[ "$SSH_OK" != "true" ]]; then
    echo "$SSH_RESULT"
    exit 1
fi

# Étape 3 : Boîte mail
MAIL_RESULT=$(bash "$SCRIPT_DIR/create_mailbox.sh" "email=${EMAIL}" "username=${USERNAME}")
MAIL_OK=$(echo "$MAIL_RESULT" | grep -o '"success":[^,]*' | cut -d: -f2)

if [[ "$MAIL_OK" != "true" ]]; then
    echo "$MAIL_RESULT"
    exit 1
fi

# Fusion des résultats en un seul JSON
LINUX_PASS=$(echo "$LINUX_RESULT" | grep -o '"linux_password":"[^"]*"' | cut -d'"' -f4)
SSH_HOST=$(echo "$SSH_RESULT" | grep -o '"ssh_host":"[^"]*"' | cut -d'"' -f4)
SSH_PORT=$(echo "$SSH_RESULT" | grep -o '"ssh_port":[0-9]*' | cut -d: -f2)
SSH_PASS=$(echo "$SSH_RESULT" | grep -o '"ssh_password":"[^"]*"' | cut -d'"' -f4)
SMTP_HOST=$(echo "$MAIL_RESULT" | grep -o '"smtp_host":"[^"]*"' | cut -d'"' -f4)
SMTP_PORT=$(echo "$MAIL_RESULT" | grep -o '"smtp_port":[0-9]*' | cut -d: -f2)
SMTP_PASS=$(echo "$MAIL_RESULT" | grep -o '"smtp_password":"[^"]*"' | cut -d'"' -f4)

cat << EOF
{"success":true,"data":{"user_id":"${USER_ID:-0}","service_id":"${SERVICE_ID:-0}","linux_username":"${USERNAME}","home_directory":"/home/${USERNAME}","linux_password":"${LINUX_PASS}","ssh_host":"${SSH_HOST}","ssh_port":${SSH_PORT:-22},"ssh_username":"${USERNAME}","ssh_password":"${SSH_PASS}","email":"${EMAIL}","smtp_host":"${SMTP_HOST}","smtp_port":${SMTP_PORT:-587},"smtp_username":"${EMAIL}","smtp_password":"${SMTP_PASS}","smtp_encryption":"tls"}}
EOF
