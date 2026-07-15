#!/bin/bash
# =============================================================================
# Déploiement Frontend O2Switch — wrapper vers deploy-o2switch.sh
# Déploie les 3 sites : vitrine + console + admin
# =============================================================================
exec "$(cd "$(dirname "$0")" && pwd)/deploy-o2switch.sh" "$@"
