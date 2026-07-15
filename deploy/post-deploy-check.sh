#!/bin/bash
# =============================================================================
# Vérification post-déploiement — AdmHost multi-domaines
# Usage : bash deploy/post-deploy-check.sh
# =============================================================================

set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
# shellcheck source=domains.env
[ -f "$SCRIPT_DIR/domains.env" ] && source "$SCRIPT_DIR/domains.env"

PASS=0
FAIL=0

VITRINE_URL="${VITRINE_URL:-https://admhost.fr}"
CONSOLE_URL="${CONSOLE_URL:-https://console.admhost.fr}"
ADMIN_URL="${ADMIN_URL:-https://manage.console.admhost.fr}"
API_URL="${API_URL:-https://api.admhost.fr}"
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

check "API health"           "${HTTP_API}/api/health" "200"
check "Vitrine accueil"      "${VITRINE_URL}/" "200"
check "Vitrine tarifs"       "${VITRINE_URL}/pricing" "200"
check "Console login"        "${CONSOLE_URL}/login" "200"
check "Console register"     "${CONSOLE_URL}/register" "200"
check "Admin login"          "${ADMIN_URL}/login" "200"

if [ "$API_URL" != "$HTTP_API" ]; then
    check "API health HTTPS" "$API_URL/api/health" "200"
fi

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
