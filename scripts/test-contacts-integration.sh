#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(
    cd "$(dirname "${BASH_SOURCE[0]}")/.."
    pwd
)"

cd "$ROOT_DIR"

BASE_URL="${BASE_URL:-http://127.0.0.1:8000}"

TMP_DIR="$(mktemp -d)"
COOKIE_JAR="$TMP_DIR/cookies.txt"

touch "$COOKIE_JAR"

USERNAME="contacts_integration_$(date +%s)_$$"
PASSWORD="Contacts-Integration-2026-$$"

USER_ID=""
CONTACT_ID=""
EVENT_ID=""

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

        fail \
            "$description: esperado HTTP $expected, recebido $actual."
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

assert_raw_contact() {
    local file="$1"
    local expected_name="$2"
    local expected_user_id="$3"

    python3 - \
        "$file" \
        "$expected_name" \
        "$expected_user_id" <<'PY'
import json
import sys

path = sys.argv[1]
expected_name = sys.argv[2]
expected_user_id = int(sys.argv[3])

with open(path, "r", encoding="utf-8") as handle:
    payload = json.load(handle)

if not isinstance(payload, dict):
    raise SystemExit(
        "Contato deveria ser um objeto JSON."
    )

if "success" in payload or "data" in payload:
    raise SystemExit(
        "Contato foi encapsulado em wrapper inesperado."
    )

if not isinstance(payload.get("id"), int):
    raise SystemExit(
        "Contato não retornou id inteiro."
    )

if payload.get("user_id") != expected_user_id:
    raise SystemExit(
        "Contato retornou user_id incorreto."
    )

if payload.get("name") != expected_name:
    raise SystemExit(
        "Contato retornou nome incorreto."
    )
PY
}

assert_list_empty() {
    local file="$1"

    python3 - "$file" <<'PY'
import json
import sys

with open(sys.argv[1], "r", encoding="utf-8") as handle:
    payload = json.load(handle)

if payload != []:
    raise SystemExit(
        f"Esperada lista vazia; recebido: {payload!r}"
    )
PY
}

assert_list_contains_contact() {
    local file="$1"
    local contact_id="$2"
    local expected_name="$3"

    python3 - \
        "$file" \
        "$contact_id" \
        "$expected_name" <<'PY'
import json
import sys

path = sys.argv[1]
contact_id = int(sys.argv[2])
expected_name = sys.argv[3]

with open(path, "r", encoding="utf-8") as handle:
    payload = json.load(handle)

if not isinstance(payload, list):
    raise SystemExit(
        "Listagem de contatos deveria ser array JSON."
    )

matches = [
    contact
    for contact in payload
    if isinstance(contact, dict)
    and contact.get("id") == contact_id
]

if len(matches) != 1:
    raise SystemExit(
        "Contato esperado não apareceu exatamente uma vez."
    )

if matches[0].get("name") != expected_name:
    raise SystemExit(
        "Contato listado possui nome inesperado."
    )
PY
}

assert_list_absent_contact() {
    local file="$1"
    local contact_id="$2"

    python3 - \
        "$file" \
        "$contact_id" <<'PY'
import json
import sys

path = sys.argv[1]
contact_id = int(sys.argv[2])

with open(path, "r", encoding="utf-8") as handle:
    payload = json.load(handle)

if not isinstance(payload, list):
    raise SystemExit(
        "Listagem de contatos deveria ser array JSON."
    )

for contact in payload:
    if (
        isinstance(contact, dict)
        and contact.get("id") == contact_id
    ):
        raise SystemExit(
            "Contato excluído ainda apareceu na listagem."
        )
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

prepare_event_link() {
    TEST_USER_ID="$USER_ID" \
    TEST_CONTACT_ID="$CONTACT_ID" \
    php <<'PHP'
<?php

declare(strict_types=1);

use AgendaInteligente\Infrastructure\Database\Connection;

require_once 'backend/autoload.php';

$application =
    require 'backend/bootstrap.php';

$pdo = Connection::make(
    $application['database']
);

$userId =
    filter_var(
        getenv('TEST_USER_ID'),
        FILTER_VALIDATE_INT
    );

$contactId =
    filter_var(
        getenv('TEST_CONTACT_ID'),
        FILTER_VALIDATE_INT
    );

if (
    $userId === false
    || $contactId === false
) {
    throw new RuntimeException(
        'IDs inválidos para fixture de evento.'
    );
}

$pdo->beginTransaction();

try {
    $statement =
        $pdo->prepare(
            '
            INSERT INTO events (
                user_id,
                title,
                event_date
            )
            VALUES (
                :user_id,
                :title,
                :event_date
            )
            '
        );

    $statement->execute([
        ':user_id' => $userId,
        ':title' =>
            'Evento integração Contatos',
        ':event_date' =>
            '2026-08-10',
    ]);

    $eventId =
        (int) $pdo->lastInsertId();

    $statement =
        $pdo->prepare(
            '
            INSERT INTO event_contacts (
                event_id,
                contact_id,
                created_at
            )
            VALUES (
                :event_id,
                :contact_id,
                UTC_TIMESTAMP()
            )
            '
        );

    $statement->execute([
        ':event_id' => $eventId,
        ':contact_id' => $contactId,
    ]);

    $pdo->commit();

    fwrite(
        STDOUT,
        (string) $eventId
    );
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    throw $exception;
}
PHP
}

assert_physical_delete_state() {
    TEST_CONTACT_ID="$CONTACT_ID" \
    TEST_EVENT_ID="$EVENT_ID" \
    php <<'PHP'
<?php

declare(strict_types=1);

use AgendaInteligente\Infrastructure\Database\Connection;

require_once 'backend/autoload.php';

$application =
    require 'backend/bootstrap.php';

$pdo = Connection::make(
    $application['database']
);

$contactId =
    (int) getenv(
        'TEST_CONTACT_ID'
    );

$eventId =
    (int) getenv(
        'TEST_EVENT_ID'
    );

$contactStatement =
    $pdo->prepare(
        '
        SELECT COUNT(*)
        FROM contacts
        WHERE id = :id
        '
    );

$contactStatement->execute([
    ':id' => $contactId,
]);

$contactCount =
    (int) $contactStatement
        ->fetchColumn();

$linkStatement =
    $pdo->prepare(
        '
        SELECT COUNT(*)
        FROM event_contacts
        WHERE event_id = :event_id
          AND contact_id = :contact_id
        '
    );

$linkStatement->execute([
    ':event_id' => $eventId,
    ':contact_id' => $contactId,
]);

$linkCount =
    (int) $linkStatement
        ->fetchColumn();

$eventStatement =
    $pdo->prepare(
        '
        SELECT COUNT(*)
        FROM events
        WHERE id = :id
        '
    );

$eventStatement->execute([
    ':id' => $eventId,
]);

$eventCount =
    (int) $eventStatement
        ->fetchColumn();

if ($contactCount !== 0) {
    throw new RuntimeException(
        'Contato ainda existe fisicamente no banco.'
    );
}

if ($linkCount !== 0) {
    throw new RuntimeException(
        'event_contacts não foi removido pelo cascade.'
    );
}

if ($eventCount !== 1) {
    throw new RuntimeException(
        'Evento relacionado foi removido indevidamente.'
    );
}
PHP
}

cleanup_database() {
    if [[ -z "${AGENDA_CONFIG_FILE:-}" ]]; then
        return
    fi

    TEST_EVENT_ID="${EVENT_ID:-}" \
    TEST_CONTACT_ID="${CONTACT_ID:-}" \
    TEST_USER_ID="${USER_ID:-}" \
    php <<'PHP' >/dev/null 2>&1 || true
<?php

declare(strict_types=1);

use AgendaInteligente\Infrastructure\Database\Connection;

require_once 'backend/autoload.php';

$application =
    require 'backend/bootstrap.php';

$pdo = Connection::make(
    $application['database']
);

$eventId =
    filter_var(
        getenv('TEST_EVENT_ID'),
        FILTER_VALIDATE_INT
    );

$contactId =
    filter_var(
        getenv('TEST_CONTACT_ID'),
        FILTER_VALIDATE_INT
    );

$userId =
    filter_var(
        getenv('TEST_USER_ID'),
        FILTER_VALIDATE_INT
    );

if ($eventId !== false) {
    $statement =
        $pdo->prepare(
            '
            DELETE FROM events
            WHERE id = :id
            '
        );

    $statement->execute([
        ':id' => $eventId,
    ]);
}

if ($contactId !== false) {
    $statement =
        $pdo->prepare(
            '
            DELETE FROM contacts
            WHERE id = :id
            '
        );

    $statement->execute([
        ':id' => $contactId,
    ]);
}

if ($userId !== false) {
    $statement =
        $pdo->prepare(
            '
            UPDATE users
            SET active = 0,
                deleted_at = UTC_TIMESTAMP(),
                updated_at = UTC_TIMESTAMP()
            WHERE id = :id
              AND deleted_at IS NULL
            '
        );

    $statement->execute([
        ':id' => $userId,
    ]);
}
PHP
}

cleanup() {
    cleanup_database
    rm -rf "$TMP_DIR"
}

trap cleanup EXIT

echo "===================================================="
echo " Agenda Inteligente — integração HTTP de Contatos"
echo "===================================================="
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
# 2. Contatos exige autenticação.
#
STATUS="$(
    request GET /api/contacts \
        -H 'Accept: application/json'
)"

assert_status \
    401 \
    "$STATUS" \
    "listagem anônima é rejeitada"

#
# 3. CSRF anônimo.
#
STATUS="$(
    request GET /auth/csrf \
        -H 'Accept: application/json'
)"

assert_status \
    200 \
    "$STATUS" \
    "obtém CSRF anônimo"

CSRF_A="$(
    json_value \
        "$TMP_DIR/response.json" \
        csrf_token
)"

[[ ${#CSRF_A} -eq 64 ]] \
    || fail \
        "Token CSRF inicial não possui 64 caracteres."

ok "token CSRF inicial possui formato esperado"

#
# 4. Registro.
#
REGISTER_BODY="$(
    python3 - \
        "$USERNAME" \
        "$PASSWORD" <<'PY'
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
    "registra usuário temporário"

#
# 5. Login.
#
STATUS="$(
    request POST /auth/login \
        -H 'Accept: application/json' \
        -H 'Content-Type: application/json' \
        -H "X-CSRF-Token: $CSRF_A" \
        --data "$REGISTER_BODY"
)"

assert_status \
    200 \
    "$STATUS" \
    "autentica usuário temporário"

USER_ID="$(
    json_value \
        "$TMP_DIR/response.json" \
        data user id
)"

[[ "$USER_ID" =~ ^[1-9][0-9]*$ ]] \
    || fail \
        "Login não retornou user id válido."

ok "login retornou identificador de usuário"

#
# 6. CSRF autenticado.
#
STATUS="$(
    request GET /auth/csrf \
        -H 'Accept: application/json'
)"

assert_status \
    200 \
    "$STATUS" \
    "obtém CSRF autenticado"

CSRF_B="$(
    json_value \
        "$TMP_DIR/response.json" \
        csrf_token
)"

[[ ${#CSRF_B} -eq 64 ]] \
    || fail \
        "Token CSRF autenticado possui formato inválido."

[[ "$CSRF_B" != "$CSRF_A" ]] \
    || fail \
        "CSRF não foi rotacionado no login."

ok "CSRF autenticado foi rotacionado"

#
# 7. Usuário novo não possui contatos.
#
STATUS="$(
    request GET /api/contacts \
        -H 'Accept: application/json'
)"

assert_status \
    200 \
    "$STATUS" \
    "lista contatos autenticados"

assert_list_empty \
    "$TMP_DIR/response.json"

ok "usuário novo inicia sem contatos"

#
# 8. Mutação autenticada sem CSRF deve falhar.
#
STATUS="$(
    request POST /api/contacts \
        -H 'Accept: application/json' \
        -H 'Content-Type: application/json' \
        --data '{"name":"Sem CSRF"}'
)"

assert_status \
    403 \
    "$STATUS" \
    "criação autenticada sem CSRF é rejeitada"

#
# 9. Criação real.
#
CREATE_BODY="$(
    python3 - "$USER_ID" <<'PY'
import json
import sys

print(json.dumps({
    "user_id": 999999999,
    "name": "Contato HTTP",
    "phone": "(12) 99999-0001",
    "email": "contato-http@example.test",
    "cep": "12345-678",
    "logradouro": "Rua da Integração",
    "numero": "100",
    "bairro": "Centro",
    "cidade": "São José dos Campos",
    "uf": "SP",
}, ensure_ascii=False))
PY
)"

STATUS="$(
    request POST /api/contacts \
        -H 'Accept: application/json' \
        -H 'Content-Type: application/json' \
        -H "X-CSRF-Token: $CSRF_B" \
        --data "$CREATE_BODY"
)"

assert_status \
    201 \
    "$STATUS" \
    "cria contato autenticado"

assert_raw_contact \
    "$TMP_DIR/response.json" \
    "Contato HTTP" \
    "$USER_ID"

CONTACT_ID="$(
    json_value \
        "$TMP_DIR/response.json" \
        id
)"

[[ "$CONTACT_ID" =~ ^[1-9][0-9]*$ ]] \
    || fail \
        "Criação não retornou contact id válido."

ok "criação retorna contrato cru"
ok "user_id enviado pelo cliente foi ignorado"

#
# 10. Listagem contém somente o contato criado.
#
STATUS="$(
    request GET /api/contacts \
        -H 'Accept: application/json'
)"

assert_status \
    200 \
    "$STATUS" \
    "lista contato criado"

assert_list_contains_contact \
    "$TMP_DIR/response.json" \
    "$CONTACT_ID" \
    "Contato HTTP"

ok "listagem contém o contato criado"

#
# 11. Atualização completa.
#
UPDATE_BODY="$(
    python3 <<'PY'
import json

print(json.dumps({
    "name": "Contato HTTP atualizado",
    "phone": "(12) 98888-0002",
    "email": "contato-atualizado@example.test",
    "cep": "87654-321",
    "logradouro": "Avenida da Integração",
    "numero": "200",
    "bairro": "Jardim Teste",
    "cidade": "São José dos Campos",
    "uf": "SP",
}, ensure_ascii=False))
PY
)"

STATUS="$(
    request PUT "/api/contacts/$CONTACT_ID" \
        -H 'Accept: application/json' \
        -H 'Content-Type: application/json' \
        -H "X-CSRF-Token: $CSRF_B" \
        --data "$UPDATE_BODY"
)"

assert_status \
    200 \
    "$STATUS" \
    "atualiza contato autenticado"

assert_raw_contact \
    "$TMP_DIR/response.json" \
    "Contato HTTP atualizado" \
    "$USER_ID"

ok "atualização retorna contrato cru"

#
# 12. Criar evento e associação diretamente no banco.
#
EVENT_ID="$(
    prepare_event_link
)"

[[ "$EVENT_ID" =~ ^[1-9][0-9]*$ ]] \
    || fail \
        "Fixture não retornou event id válido."

ok "fixture criou associação event_contacts"

#
# 13. Exclusão HTTP.
#
STATUS="$(
    request DELETE "/api/contacts/$CONTACT_ID" \
        -H 'Accept: application/json' \
        -H "X-CSRF-Token: $CSRF_B"
)"

assert_status \
    200 \
    "$STATUS" \
    "remove contato autenticado"

DELETE_MESSAGE="$(
    json_value \
        "$TMP_DIR/response.json" \
        message
)"

[[ "$DELETE_MESSAGE" == "Contato removido" ]] \
    || fail \
        "DELETE retornou mensagem inesperada."

ok "DELETE preserva contrato legado"

#
# 14. Provar exclusão física + cascade + evento preservado.
#
assert_physical_delete_state

ok "contato foi removido fisicamente"
ok "event_contacts foi removido por cascade"
ok "evento relacionado permaneceu existente"

#
# 15. Contato não aparece mais na API.
#
STATUS="$(
    request GET /api/contacts \
        -H 'Accept: application/json'
)"

assert_status \
    200 \
    "$STATUS" \
    "lista contatos após exclusão"

assert_list_absent_contact \
    "$TMP_DIR/response.json" \
    "$CONTACT_ID"

ok "contato excluído não aparece na listagem"

#
# 16. Segunda exclusão deve retornar não encontrado.
#
STATUS="$(
    request DELETE "/api/contacts/$CONTACT_ID" \
        -H 'Accept: application/json' \
        -H "X-CSRF-Token: $CSRF_B"
)"

assert_status \
    404 \
    "$STATUS" \
    "segunda exclusão retorna não encontrado"

NOT_FOUND_MESSAGE="$(
    json_value \
        "$TMP_DIR/response.json" \
        error
)"

[[ "$NOT_FOUND_MESSAGE" == "Contato não encontrado" ]] \
    || fail \
        "404 retornou mensagem inesperada."

ok "não encontrado preserva contrato externo"

echo
echo "===================================================="
echo " TODOS OS TESTES DE CONTATOS PASSARAM"
echo "===================================================="
