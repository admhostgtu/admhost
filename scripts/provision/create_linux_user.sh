#!/bin/bash
# =============================================================================
# Crée un utilisateur Linux via sudo (utilisateur provisioner, pas root direct).
# Usage : bash create_linux_user.sh username=john user_id=1
# =============================================================================
set -euo pipefail

log() { echo "[$(date -Iseconds)] $*" >&2; }

for arg in "$@"; do
    key="${arg%%=*}"
    val="${arg#*=}"
    case "$key" in
        username) USERNAME="$val" ;;
        user_id)  USER_ID="$val" ;;
    esac
done

if [[ ! "${USERNAME:-}" =~ ^[a-z][a-z0-9_]{2,31}$ ]]; then
    echo '{"success":false,"error":"Username invalide"}'
    exit 1
fi

HOME_DIR="/home/${USERNAME}"
RECORD_DIR="/var/lib/admhost/users"

_run_as_root() {
    if [[ "$(id -u)" -eq 0 ]]; then
        "$@"
    elif command -v sudo &>/dev/null; then
        sudo -n "$@"
    else
        log "WARN: pas de sudo — mode simulation"
        return 1
    fi
}

# Mode simulation (dev / Windows / sans sudo)
if ! _run_as_root true 2>/dev/null; then
    RANDOM_PASS=$(openssl rand -hex 16 2>/dev/null || head -c 16 /dev/urandom | xxd -p)
    echo "{\"success\":true,\"data\":{\"linux_username\":\"${USERNAME}\",\"home_directory\":\"${HOME_DIR}\",\"linux_password\":\"${RANDOM_PASS}\",\"user_id\":\"${USER_ID:-0}\",\"mode\":\"simulated\"}}"
    exit 0
fi

if _run_as_root id "$USERNAME" &>/dev/null; then
    log "INFO: utilisateur $USERNAME existe déjà"
    echo "{\"success\":true,\"data\":{\"linux_username\":\"${USERNAME}\",\"home_directory\":\"${HOME_DIR}\",\"mode\":\"existing\"}}"
    exit 0
fi

RANDOM_PASS=$(openssl rand -hex 16)
_run_as_root useradd -m -s /bin/bash "$USERNAME"
echo "${USERNAME}:${RANDOM_PASS}" | _run_as_root chpasswd

_run_as_root mkdir -p "$RECORD_DIR"
RECORD_FILE="${RECORD_DIR}/${USER_ID}_${USERNAME}.json"
echo "{\"linux_username\":\"${USERNAME}\",\"home_directory\":\"${HOME_DIR}\",\"created_at\":\"$(date -Iseconds)\",\"user_id\":\"${USER_ID}\"}" \
    | _run_as_root tee "$RECORD_FILE" > /dev/null
_run_as_root chmod 600 "$RECORD_FILE"

log "INFO: utilisateur $USERNAME créé"
echo "{\"success\":true,\"data\":{\"linux_username\":\"${USERNAME}\",\"home_directory\":\"${HOME_DIR}\",\"linux_password\":\"${RANDOM_PASS}\",\"user_id\":\"${USER_ID}\",\"record_file\":\"${RECORD_FILE}\"}}"
