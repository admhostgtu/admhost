#!/bin/bash
# =============================================================================
# Vérification post-déploiement
# Usage :
#   bash deploy/post-deploy-check.sh
#   API_URL=http://api.tondomaine.com bash deploy/post-deploy-check.sh
# =============================================================================

set -e

PASS=0
FAIL=0

APP_URL="${APP_URL:-https://tondomaine.com}"
API_URL="${API_URL:-https://api.tondomaine.com}"

# Si HTTPS échoue, tenter HTTP (avant certbot)
HTTP_API="${API_URL/https:/http:}"

check() {
    local label="$1"
    local url="$2"
    local expected="${3:-200}"

    CODE=$(curl -s -o /dev/null -w "%{http_code}" --max-time 10 "$url" 2>/dev/null || echo "000")

    if [ "$CODE" = "$expected" ]; then
        echo "✅ [OK]   $label → HTTP $CODE"
        PASS=$((PASS + 1))
    else
        echo "❌ [FAIL] $label → HTTP $CODE (attendu $expected) — $url"
        FAIL=$((FAIL + 1))
    fi
}

echo "=== AdmHost — Post-déploiement ==="
echo ""

# Backend — HTTP prioritaire
check "Backend health (HTTP)"  "${HTTP_API}/api/health" "200"
check "Backend root (HTTP)"    "${HTTP_API}/"           "200"

# Backend HTTPS (si certbot déjà fait)
if [ "$API_URL" != "$HTTP_API" ]; then
    check "Backend health (HTTPS)" "$API_URL/api/health" "200"
fi

# Frontend
check "Frontend accueil"  "$APP_URL/"    "200"
check "Frontend login"    "$APP_URL/login" "200"

# JSON
BODY=$(curl -s --max-time 10 "${HTTP_API}/api/health" 2>/dev/null || echo "")
if echo "$BODY" | grep -q '"status"'; then
    echo "✅ [OK]   API JSON valide"
    PASS=$((PASS + 1))
else
    echo "❌ [FAIL] API JSON invalide"
    FAIL=$((FAIL + 1))
fi

echo ""
echo "Résultat : $PASS OK, $FAIL échecs"

[ "$FAIL" -eq 0 ] && exit 0 || exit 1
