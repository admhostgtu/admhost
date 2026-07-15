#!/bin/bash
# Provisionne un conteneur Docker isolé pour un client AdmHost
# Usage : bash create_docker_service.sh user_id=1 service_id=5 username=john subdomain=john

set -euo pipefail

USER_ID=""
SERVICE_ID=""
USERNAME=""
SUBDOMAIN=""

for arg in "$@"; do
    key="${arg%%=*}"
    val="${arg#*=}"
    case "$key" in
        user_id)    USER_ID="$val" ;;
        service_id) SERVICE_ID="$val" ;;
        username)   USERNAME="$val" ;;
        subdomain)  SUBDOMAIN="$val" ;;
    esac
done

if [[ -z "$USERNAME" || -z "$SUBDOMAIN" ]]; then
    echo '{"success":false,"error":"username et subdomain requis"}'
    exit 1
fi

SUFFIX="${USER_SUBDOMAIN_SUFFIX:-clients.admhost.fr}"
FULL_DOMAIN="${SUBDOMAIN}.${SUFFIX}"
CONTAINER_NAME="admhost-${USERNAME}"
IMAGE="${DOCKER_DEFAULT_IMAGE:-nginx:alpine}"
WEB_PORT=$(( 18000 + (USER_ID % 1000) ))

if [[ "$EUID" -ne 0 ]] && ! command -v docker >/dev/null 2>&1; then
    echo "{\"success\":true,\"data\":{\"subdomain\":\"${SUBDOMAIN}\",\"web_url\":\"https://${FULL_DOMAIN}\",\"docker_container_id\":\"${CONTAINER_NAME}\",\"docker_image\":\"${IMAGE}\",\"ssh_host\":\"${FULL_DOMAIN}\",\"ssh_port\":22,\"ssh_username\":\"${USERNAME}\",\"ssh_password\":\"DockerSim123!\",\"mode\":\"simulated\"}}"
    exit 0
fi

# Arrêter conteneur existant si présent
docker rm -f "$CONTAINER_NAME" 2>/dev/null || true

docker run -d \
    --name "$CONTAINER_NAME" \
    --label "admhost.user_id=${USER_ID}" \
    --label "admhost.service_id=${SERVICE_ID}" \
    --restart unless-stopped \
    -p "127.0.0.1:${WEB_PORT}:80" \
    "$IMAGE" >/dev/null 2>&1 || {
    echo '{"success":false,"error":"Échec création conteneur Docker"}'
    exit 1
}

echo "{\"success\":true,\"data\":{\"subdomain\":\"${SUBDOMAIN}\",\"web_url\":\"https://${FULL_DOMAIN}\",\"docker_container_id\":\"${CONTAINER_NAME}\",\"docker_image\":\"${IMAGE}\",\"metadata\":{\"internal_port\":${WEB_PORT},\"domain\":\"${FULL_DOMAIN}\"}}}"
