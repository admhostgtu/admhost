#!/bin/bash
# Déploiement complet — VPS Scaleway uniquement
exec "$(cd "$(dirname "$0")" && pwd)/deploy-vps.sh" "$@"
