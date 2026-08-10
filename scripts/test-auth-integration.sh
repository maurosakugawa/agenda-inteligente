#!/usr/bin/env bash

set -euo pipefail

BASE_URL="${BASE_URL:-http://127.0.0.1:8000}"

TMP_DIR="$(mktemp -d)"
COOKIE_JAR="$TMP_DIR/cookies.txt"

touch "$COOKIE_JAR"

trap 'rm -rf "$TMP_DIR"' EXIT

USERNAME="integration_$(date +%s)_$$"
PASSWORD="Integration-Auth-2026-$$"

fail() {
    echo
    echo "[FALHA] $*" >&2
    exit 1
}

ok() {
    echo "[OK] $*"
}

assert_status() {
    local expected="$1"
    local actual="$2"
    local description="$3"

    if [[ "$actual" != "$expected" ]]; then
        echo
        echo "Resposta recebida:"
        cat "$TMP_DIR/response.json" 2>/dev/null || true
        echo

        fail "$description: esperado HTTP $expected, recebido $actual."
    fi

    ok "$description"
}

json_value() {
    local file="$1"
    shift

    python3 - "$file" "$@" <<'PY'
import json
import sys

path = sys.argv[1]
keys = sys.argv[2:]

with open(path, "r", encoding="utf-8") as handle:
    value = json.load(handle)

for key in keys:
    if not isinstance(value, dict) or key not in value:
        raise SystemExit(
            f"Campo JSON ausente: {'.'.join(keys)}"
        )

    value = value[key]

if isinstance(value, bool):
    print("true" if value else "false")
elif value is None:
    print("")
else:
    print(value)
PY
}

request() {
    local method="$1"
    local path="$2"
    shift 2

    curl \
        --silent \
        --show-error \
        --output "$TMP_DIR/response.json" \
        --write-out '%{http_code}' \
        --cookie "$COOKIE_JAR" \
        --cookie-jar "$COOKIE_JAR" \
        --request "$method" \
        "$@" \
        "$BASE_URL$path"
}

echo "=============================================="
echo " Agenda Inteligente — integração Auth/CSRF"
echo "=============================================="
echo
echo "Backend: $BASE_URL"
echo "Usuário temporário: $USERNAME"
echo

#
# 1. Health
#
STATUS="$(
    request GET /api/health
)"

assert_status \
    200 \
    "$STATUS" \
    "health responde"

#
# 2. Sessão anônima + primeiro CSRF
#
STATUS="$(
    request GET /auth/csrf \
        -H 'Accept: application/json'
)"

assert_status \
    200 \
    "$STATUS" \
    "obtém CSRF da sessão anônima"

CSRF_A="$(
    json_value \
        "$TMP_DIR/response.json" \
        csrf_token
)"

[[ ${#CSRF_A} -eq 64 ]] \
    || fail "Token CSRF inicial não possui 64 caracteres."

ok "token CSRF inicial possui formato esperado"

#
# 3. Registro usando a mesma sessão anônima
#
REGISTER_BODY="$(
    python3 - "$USERNAME" "$PASSWORD" <<'PY'
import json
import sys

print(json.dumps({
    "username": sys.argv[1],
    "password": sys.argv[2],
}))
PY
)"

STATUS="$(
    request POST /auth/register \
        -H 'Accept: application/json' \
        -H 'Content-Type: application/json' \
        -H "X-CSRF-Token: $CSRF_A" \
        --data "$REGISTER_BODY"
)"

assert_status \
    201 \
    "$STATUS" \
    "registro exige e aceita CSRF"

REGISTER_SUCCESS="$(
    json_value \
        "$TMP_DIR/response.json" \
        success
)"

[[ "$REGISTER_SUCCESS" == "true" ]] \
    || fail "Registro não retornou success=true."

ok "contrato de registro está correto"

#
# 4. Login usando CSRF da sessão anônima
#
LOGIN_BODY="$REGISTER_BODY"

STATUS="$(
    request POST /auth/login \
        -H 'Accept: application/json' \
        -H 'Content-Type: application/json' \
        -H "X-CSRF-Token: $CSRF_A" \
        --data "$LOGIN_BODY"
)"

assert_status \
    200 \
    "$STATUS" \
    "login com CSRF funciona"

LOGIN_USER="$(
    json_value \
        "$TMP_DIR/response.json" \
        data user username
)"

[[ "$LOGIN_USER" == "$USERNAME" ]] \
    || fail "Login retornou usuário inesperado."

CSRF_LOGIN="$(
    json_value \
        "$TMP_DIR/response.json" \
        data csrf_token
)"

[[ "$CSRF_LOGIN" != "$CSRF_A" ]] \
    || fail "CSRF não foi rotacionado após login."

ok "login retornou identidade correta"
ok "sessão/login rotacionou o CSRF"

#
# 5. Frontend faz exatamente isto depois do login:
#    GET /auth/csrf novamente
#
STATUS="$(
    request GET /auth/csrf \
        -H 'Accept: application/json'
)"

assert_status \
    200 \
    "$STATUS" \
    "obtém CSRF da sessão autenticada"

CSRF_B="$(
    json_value \
        "$TMP_DIR/response.json" \
        csrf_token
)"

[[ "$CSRF_B" != "$CSRF_A" ]] \
    || fail "Token pós-login é igual ao token anônimo."

[[ "$CSRF_B" == "$CSRF_LOGIN" ]] \
    || fail "GET /auth/csrf não retornou o token da sessão autenticada."

ok "refresh pós-login retorna o token autenticado"

#
# 6. Token anterior deve estar revogado.
#
STATUS="$(
    request POST /auth/logout \
        -H 'Accept: application/json' \
        -H "X-CSRF-Token: $CSRF_A"
)"

assert_status \
    403 \
    "$STATUS" \
    "token CSRF anterior é rejeitado"

#
# 7. Sessão continua autenticada após a tentativa inválida.
#
STATUS="$(
    request GET /auth/me \
        -H 'Accept: application/json'
)"

assert_status \
    200 \
    "$STATUS" \
    "/auth/me reconhece sessão autenticada"

ME_USER="$(
    json_value \
        "$TMP_DIR/response.json" \
        username
)"

[[ "$ME_USER" == "$USERNAME" ]] \
    || fail "/auth/me retornou usuário inesperado."

ok "contrato direto de /auth/me está correto"

#
# 8. Logout com o token da sessão autenticada.
#
STATUS="$(
    request POST /auth/logout \
        -H 'Accept: application/json' \
        -H "X-CSRF-Token: $CSRF_B"
)"

assert_status \
    200 \
    "$STATUS" \
    "logout com CSRF autenticado funciona"

#
# 9. Sessão deve estar encerrada.
#
STATUS="$(
    request GET /auth/me \
        -H 'Accept: application/json'
)"

assert_status \
    401 \
    "$STATUS" \
    "/auth/me rejeita sessão após logout"

echo
echo "=============================================="
echo " TODOS OS TESTES DE INTEGRAÇÃO PASSARAM"
echo "=============================================="
echo
echo "Credenciais criadas no banco de TESTE:"
echo "  username: $USERNAME"
echo "  password: $PASSWORD"
echo
