#!/bin/bash
# =============================================================================
# Configure l'accès SSH pour un utilisateur Linux.
# Usage : bash create_ssh_access.sh username=john
# Sortie  : JSON sur stdout
# =============================================================================
set -euo pipefail

for arg in "$@"; do
    key="${arg%%=*}"
    val="${arg#*=}"
    case "$key" in
        username) USERNAME="$val" ;;
    esac
done

if [[ ! "${USERNAME:-}" =~ ^[a-z][a-z0-9_]{2,31}$ ]]; then
    echo '{"success":false,"error":"Username invalide"}'
    exit 1
fi

SSH_HOST="${SSH_HOST:-$(hostname -f 2>/dev/null || echo localhost)}"
SSH_PORT="${SSH_PORT:-22}"
SSH_PASSWORD=$(openssl rand -base64 16 2>/dev/null || head -c 16 /dev/urandom | base64)

# Mode simulation
if [[ "$(id -u)" -ne 0 ]]; then
    echo "{\"success\":true,\"data\":{\"ssh_host\":\"${SSH_HOST}\",\"ssh_port\":${SSH_PORT},\"ssh_username\":\"${USERNAME}\",\"ssh_password\":\"${SSH_PASSWORD}\",\"mode\":\"simulated\"}}"
    exit 0
fi

# Génération clé SSH (optionnelle)
SSH_DIR="/home/${USERNAME}/.ssh"
mkdir -p "$SSH_DIR"
ssh-keygen -t ed25519 -f "${SSH_DIR}/id_ed25519" -N "" -q 2>/dev/null || true
chown -R "${USERNAME}:${USERNAME}" "$SSH_DIR"
chmod 700 "$SSH_DIR"

# Mise à jour mot de passe SSH
echo "${USERNAME}:${SSH_PASSWORD}" | chpasswd

echo "{\"success\":true,\"data\":{\"ssh_host\":\"${SSH_HOST}\",\"ssh_port\":${SSH_PORT},\"ssh_username\":\"${USERNAME}\",\"ssh_password\":\"${SSH_PASSWORD}\"}}"
