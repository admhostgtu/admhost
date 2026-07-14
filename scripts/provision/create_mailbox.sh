#!/bin/bash
# =============================================================================
# Crée une boîte mail / accès SMTP pour un utilisateur.
# Usage : bash create_mailbox.sh email=john@domain.com username=john
# Sortie  : JSON sur stdout
# =============================================================================
set -euo pipefail

for arg in "$@"; do
    key="${arg%%=*}"
    val="${arg#*=}"
    case "$key" in
        email)    EMAIL="$val" ;;
        username) USERNAME="$val" ;;
    esac
done

# Validation email
if [[ ! "${EMAIL:-}" =~ ^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$ ]]; then
    echo '{"success":false,"error":"Email invalide"}'
    exit 1
fi

if [[ ! "${USERNAME:-}" =~ ^[a-z][a-z0-9_]{2,31}$ ]]; then
    echo '{"success":false,"error":"Username invalide"}'
    exit 1
fi

SMTP_HOST="${SMTP_HOST:-mail.localhost}"
SMTP_PORT="${SMTP_PORT:-587}"
SMTP_PASSWORD=$(openssl rand -base64 16 2>/dev/null || head -c 16 /dev/urandom | base64)

# Mode simulation
if [[ "$(id -u)" -ne 0 ]]; then
    echo "{\"success\":true,\"data\":{\"email\":\"${EMAIL}\",\"smtp_host\":\"${SMTP_HOST}\",\"smtp_port\":${SMTP_PORT},\"smtp_username\":\"${EMAIL}\",\"smtp_password\":\"${SMTP_PASSWORD}\",\"smtp_encryption\":\"tls\",\"mode\":\"simulated\"}}"
    exit 0
fi

# Exemple avec postfix/dovecot (adapter selon votre stack mail)
# useradd mail user, postmap, doveadm pw etc.
MAIL_RECORD="/var/lib/admhost/mail/${EMAIL}.json"
mkdir -p /var/lib/admhost/mail
cat > "$MAIL_RECORD" << EOF
{"email":"${EMAIL}","username":"${USERNAME}","created_at":"$(date -Iseconds)"}
EOF
chmod 600 "$MAIL_RECORD"

echo "{\"success\":true,\"data\":{\"email\":\"${EMAIL}\",\"smtp_host\":\"${SMTP_HOST}\",\"smtp_port\":${SMTP_PORT},\"smtp_username\":\"${EMAIL}\",\"smtp_password\":\"${SMTP_PASSWORD}\",\"smtp_encryption\":\"tls\",\"record_file\":\"${MAIL_RECORD}\"}}"
