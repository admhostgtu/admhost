#!/bin/bash
# Alias — déploiement VPS complet (API + frontend + admin)
exec "$(cd "$(dirname "$0")" && pwd)/deploy-vps.sh" "$@"
