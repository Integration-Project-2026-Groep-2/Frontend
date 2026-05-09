#!/usr/bin/env bash
# E2E smoke test for /registreer + login flow.
#
# Drives the form via curl against a live Drupal (default localhost:8090) and
# verifies the resulting state in MariaDB. Runs as a complement to the
# PHPUnit Unit + Kernel suites — those don't exercise the HTTP layer.
#
# Usage: ./e2e-smoke.sh [BASE_URL] [DB_CONTAINER] [DRUPAL_CONTAINER]
#   BASE_URL          — defaults to http://localhost:8090
#   DB_CONTAINER      — defaults to frontend_db
#   DRUPAL_CONTAINER  — defaults to frontend_drupal (only used for cleanup)
#
# Exit 0 = all assertions pass. Non-zero = failure with the assertion that
# broke. Cleans up the test users at the end so reruns stay clean.

set -euo pipefail

BASE="${1:-http://localhost:8090}"
DB="${2:-frontend_db}"
APP="${3:-frontend_drupal}"
MQ="${4:-rabbitmq_management}"

JAR=$(mktemp)
trap "rm -f $JAR" EXIT

EMAIL_BZ="e2e-bz-$(date +%s)@local.test"
EMAIL_CO="e2e-co-$(date +%s)@local.test"
PASS_BZ="bezoeker1234"
PASS_CO="bedrijf1234567"
QUEUE_REG="e2e-test-registration-$(date +%s)"
QUEUE_CO="e2e-test-company-$(date +%s)"

assert_eq() {
  local actual="$1" expected="$2" label="$3"
  if [[ "$actual" != "$expected" ]]; then
    echo "FAIL [$label]: expected='$expected' got='$actual'"; exit 1
  fi
  echo "  ok  [$label]: $actual"
}

assert_contains() {
  local haystack="$1" needle="$2" label="$3"
  if ! echo "$haystack" | grep -q "$needle"; then
    echo "FAIL [$label]: expected to contain '$needle' in: $haystack"; exit 1
  fi
  echo "  ok  [$label]: contains '$needle'"
}

extract_form_id() {
  # Pipefail + grep-no-match returns 1 — wrap in || true so empty match
  # is just empty string rather than fatal.
  (echo "$1" | grep -oE 'name="form_build_id" value="[^"]+"' | head -1 | sed 's/.*value="//;s/"$//') || true
}

extract_form_token() {
  (echo "$1" | grep -oE 'name="form_token" value="[^"]+"' | head -1 | sed 's/.*value="//;s/"$//') || true
}

post_register() {
  local data="$1"
  curl -s -c "$JAR" -b "$JAR" --max-time 30 -o /dev/null \
    -w "%{http_code} %{redirect_url}" \
    -X POST "$BASE/registreer" \
    --data "$data"
}

# RabbitMQ helpers — all via `docker exec` since rabbitmq_management
# doesn't always expose 15672 to the host (depends on compose state).
RABBIT_USER="${RABBITMQ_USER:-guest}"
RABBIT_PASS="${RABBITMQ_PASS:-guest}"

mq_admin() {
  docker exec "$MQ" rabbitmqadmin -u "$RABBIT_USER" -p "$RABBIT_PASS" "$@"
}

mq_declare_queue() {
  mq_admin declare queue --name="$1" --durable=true --auto-delete=false
}

mq_declare_exchange_user_topic() {
  # Idempotent — RabbitMQClient also declares this on first connect.
  mq_admin declare exchange --name=user.topic --type=topic --durable=true
}

mq_bind() {
  local queue="$1" routing_key="$2"
  mq_admin declare binding --source=user.topic --destination-type=queue \
    --destination="$queue" --routing-key="$routing_key"
}

mq_delete_queue() {
  mq_admin delete queue --name="$1" 2>/dev/null || true
}

# Drains a single message and prints the XML payload to stdout.
# Returns non-zero (and empty stdout) if the queue is empty.
# Uses docker exec frontend_drupal curl (since rabbitmq_management has no
# curl + the host port may not be published).
mq_drain_one_xml() {
  local queue="$1" body
  body=$(docker exec "$APP" curl -s -u "$RABBIT_USER:$RABBIT_PASS" \
    -X POST -H 'Content-Type: application/json' \
    "http://rabbitmq:15672/api/queues/%2F/$queue/get" \
    -d '{"count":1,"ackmode":"ack_requeue_false","encoding":"auto"}' 2>/dev/null)
  # Empty array means no messages.
  if [[ -z "$body" || "$body" == "[]" ]]; then
    return 1
  fi
  # Strip the JSON wrapper and unescape the payload field.
  # Format: [{"payload_bytes":N,...,"payload":"<xml...>","payload_encoding":"string"}]
  echo "$body" | perl -pe 's/.*"payload":"//; s/","payload_encoding":.*//; s/\\"/"/g; s/\\\//\//g; s/\\n/\n/g'
}

assert_xml_validates_against() {
  local xml_file="$1" xsd="$2" label="$3"
  if docker exec -i "$APP" sh -c "xmllint --schema /opt/drupal/$xsd --noout - >/dev/null 2>&1" < "$xml_file"; then
    echo "  ok  [$label]: XML validates against $xsd"
  else
    echo "FAIL [$label]: XSD validation failed for $xsd"
    docker exec -i "$APP" sh -c "xmllint --schema /opt/drupal/$xsd --noout -" < "$xml_file" 2>&1 | head -10
    exit 1
  fi
}

echo "=== AMQP test queues ==="
mq_declare_exchange_user_topic
mq_declare_queue "$QUEUE_REG"
mq_declare_queue "$QUEUE_CO"
mq_bind "$QUEUE_REG" "frontend.registration.created"
mq_bind "$QUEUE_CO"  "frontend.company.created"
echo "  ok  declared $QUEUE_REG + $QUEUE_CO bound to user.topic"

echo ""
echo "=== Bezoeker flow ==="
HTML=$(curl -s -c "$JAR" -b "$JAR" --max-time 10 "$BASE/registreer")
BUILD_ID=$(extract_form_id "$HTML")
TOKEN=$(extract_form_token "$HTML")
[[ -n "$BUILD_ID" ]] || { echo "FAIL: could not extract form_build_id"; exit 1; }

DATA="registratie_type=bezoeker&firstName=E2E&lastName=Bezoeker&email=$EMAIL_BZ&phone=&role=visitor&companyName=&vatNumber=&street=&city=&gdpr_consent=1&pass[pass1]=$PASS_BZ&pass[pass2]=$PASS_BZ&op=Registreer!&form_build_id=$BUILD_ID&form_token=$TOKEN&form_id=shift_bezoeker_registratie_form"
RESULT=$(post_register "$DATA")
HTTP_CODE=$(echo "$RESULT" | awk '{print $1}')
REDIRECT=$(echo "$RESULT" | awk '{print $2}')
assert_eq "$HTTP_CODE" "303" "POST /registreer status"
assert_contains "$REDIRECT" "/home" "redirect target"

UID_BZ=$(docker exec "$DB" sh -c "mariadb -u drupal -pdrupal_pass drupal -sNe \"SELECT uid FROM users_field_data WHERE mail='$EMAIL_BZ'\"" 2>&1 | tail -1)
[[ -n "$UID_BZ" && "$UID_BZ" =~ ^[0-9]+$ ]] || { echo "FAIL: bezoeker uid not found, got '$UID_BZ'"; exit 1; }
echo "  ok  bezoeker uid: $UID_BZ"

ROLE=$(docker exec "$DB" sh -c "mariadb -u drupal -pdrupal_pass drupal -sNe \"SELECT roles_target_id FROM user__roles WHERE entity_id=$UID_BZ\"" 2>&1 | tail -1)
assert_eq "$ROLE" "visitor" "bezoeker role assigned"

FNAME=$(docker exec "$DB" sh -c "mariadb -u drupal -pdrupal_pass drupal -sNe \"SELECT value FROM users_data WHERE uid=$UID_BZ AND name='first_name'\"" 2>&1 | tail -1 | tr -d '"')
assert_contains "$FNAME" "E2E" "first_name persisted via UserData"

# Drain the registration queue and validate the XML payload.
TMP_XML=$(mktemp)
trap "rm -f $JAR $TMP_XML" EXIT
if mq_drain_one_xml "$QUEUE_REG" > "$TMP_XML" && [[ -s "$TMP_XML" ]]; then
  echo "  ok  bezoeker registration message published"
  assert_contains "$(cat "$TMP_XML")" "$EMAIL_BZ" "AMQP payload contains bezoeker email"
  assert_contains "$(cat "$TMP_XML")" "<role>visitor</role>" "AMQP payload role=visitor"
  assert_xml_validates_against "$TMP_XML" "xsd/frontend-contract.xsd" "bezoeker registration XSD"
else
  echo "FAIL: no registration message drained from $QUEUE_REG"
  mq_delete_queue "$QUEUE_REG"
  mq_delete_queue "$QUEUE_CO"
  exit 1
fi

echo ""
echo "=== Logout + re-login round-trip ==="
curl -s -c "$JAR" -b "$JAR" --max-time 10 -o /dev/null "$BASE/user/logout"
LOGIN_HTML=$(curl -s -c "$JAR" -b "$JAR" --max-time 10 "$BASE/user/login")
LOGIN_BUILD=$(extract_form_id "$LOGIN_HTML")
LOGIN_TOKEN=$(extract_form_token "$LOGIN_HTML")
LOGIN_RESULT=$(curl -s -c "$JAR" -b "$JAR" --max-time 15 -o /dev/null -w "%{http_code} %{redirect_url}" \
  -X POST "$BASE/user/login" \
  --data "name=$EMAIL_BZ&pass=$PASS_BZ&op=Log+in&form_build_id=$LOGIN_BUILD&form_token=$LOGIN_TOKEN&form_id=user_login_form")
LOGIN_CODE=$(echo "$LOGIN_RESULT" | awk '{print $1}')
LOGIN_REDIRECT=$(echo "$LOGIN_RESULT" | awk '{print $2}')
# Drupal core's user.login uses 302; our shift_bezoeker form uses 303.
# Both are valid POST-redirect-GET; accept either.
if [[ "$LOGIN_CODE" != "302" && "$LOGIN_CODE" != "303" ]]; then
  echo "FAIL [POST /user/login status]: expected 302 or 303, got '$LOGIN_CODE'"; exit 1
fi
echo "  ok  [POST /user/login status]: $LOGIN_CODE"
assert_contains "$LOGIN_REDIRECT" "/home" "login redirects visitor to /home"

echo ""
echo "=== Bedrijf flow ==="
curl -s -c "$JAR" -b "$JAR" --max-time 10 -o /dev/null "$BASE/user/logout"
HTML=$(curl -s -c "$JAR" -b "$JAR" --max-time 10 "$BASE/registreer")
BUILD_ID=$(extract_form_id "$HTML")
TOKEN=$(extract_form_token "$HTML")
DATA="registratie_type=bedrijf&firstName=Lars&lastName=Cowe&email=$EMAIL_CO&phone=&role=&companyName=E2E+BV&vatNumber=BE0888777666&street=Stationsstraat+1&city=Brussel&gdpr_consent=1&pass[pass1]=$PASS_CO&pass[pass2]=$PASS_CO&op=Registreer!&form_build_id=$BUILD_ID&form_token=$TOKEN&form_id=shift_bezoeker_registratie_form"
RESULT=$(post_register "$DATA")
HTTP_CODE=$(echo "$RESULT" | awk '{print $1}')
assert_eq "$HTTP_CODE" "303" "bedrijf POST /registreer status"

UID_CO=$(docker exec "$DB" sh -c "mariadb -u drupal -pdrupal_pass drupal -sNe \"SELECT uid FROM users_field_data WHERE mail='$EMAIL_CO'\"" 2>&1 | tail -1)
[[ -n "$UID_CO" && "$UID_CO" =~ ^[0-9]+$ ]] || { echo "FAIL: bedrijf uid not found"; exit 1; }
echo "  ok  bedrijf uid: $UID_CO"

ROLE_CO=$(docker exec "$DB" sh -c "mariadb -u drupal -pdrupal_pass drupal -sNe \"SELECT roles_target_id FROM user__roles WHERE entity_id=$UID_CO\"" 2>&1 | tail -1)
assert_eq "$ROLE_CO" "company" "bedrijf role assigned"

VAT=$(docker exec "$DB" sh -c "mariadb -u drupal -pdrupal_pass drupal -sNe \"SELECT value FROM users_data WHERE uid=$UID_CO AND name='vat_number'\"" 2>&1 | tail -1 | tr -d '"')
assert_contains "$VAT" "BE0888777666" "vat_number persisted + uppercase"

CO_FNAME=$(docker exec "$DB" sh -c "mariadb -u drupal -pdrupal_pass drupal -sNe \"SELECT value FROM users_data WHERE uid=$UID_CO AND name='first_name'\"" 2>&1 | tail -1 | tr -d '"')
assert_contains "$CO_FNAME" "Lars" "bedrijf contact-persoon first_name persisted"

# Drain both queues — bedrijf publishes Registration AND CompanyCreated.
if mq_drain_one_xml "$QUEUE_REG" > "$TMP_XML" && [[ -s "$TMP_XML" ]]; then
  echo "  ok  bedrijf registration message published"
  assert_contains "$(cat "$TMP_XML")" "<role>company_contact</role>" "bedrijf registration role=company_contact"
  assert_contains "$(cat "$TMP_XML")" "<firstName>Lars</firstName>" "bedrijf registration firstName=contact-persoon (not companyName)"
  assert_xml_validates_against "$TMP_XML" "xsd/frontend-contract.xsd" "bedrijf registration XSD"
else
  echo "FAIL: no registration message drained from $QUEUE_REG (bedrijf)"
  mq_delete_queue "$QUEUE_REG"
  mq_delete_queue "$QUEUE_CO"
  exit 1
fi

if mq_drain_one_xml "$QUEUE_CO" > "$TMP_XML" && [[ -s "$TMP_XML" ]]; then
  echo "  ok  bedrijf company-created message published"
  assert_contains "$(cat "$TMP_XML")" "<vatNumber>BE0888777666</vatNumber>" "company-created vatNumber"
  assert_contains "$(cat "$TMP_XML")" "<name>E2E BV</name>" "company-created name"
  assert_xml_validates_against "$TMP_XML" "xsd/frontend-contract.xsd" "bedrijf company-created XSD"
else
  echo "FAIL: no company-created message drained from $QUEUE_CO"
  mq_delete_queue "$QUEUE_REG"
  mq_delete_queue "$QUEUE_CO"
  exit 1
fi

echo ""
echo "=== Duplicate email rejected ==="
curl -s -c "$JAR" -b "$JAR" --max-time 10 -o /dev/null "$BASE/user/logout"
HTML=$(curl -s -c "$JAR" -b "$JAR" --max-time 10 "$BASE/registreer")
BUILD_ID=$(extract_form_id "$HTML")
TOKEN=$(extract_form_token "$HTML")
DATA="registratie_type=bezoeker&firstName=Dup&lastName=Test&email=$EMAIL_BZ&phone=&role=visitor&companyName=&vatNumber=&street=&city=&gdpr_consent=1&pass[pass1]=$PASS_BZ&pass[pass2]=$PASS_BZ&op=Registreer!&form_build_id=$BUILD_ID&form_token=$TOKEN&form_id=shift_bezoeker_registratie_form"
DUP_RESULT=$(curl -s -c "$JAR" -b "$JAR" --max-time 15 -o /dev/null -w "%{http_code}" \
  -X POST "$BASE/registreer" --data "$DATA")
assert_eq "$DUP_RESULT" "200" "duplicate email returns form (no redirect)"

echo ""
echo "=== Cleanup test users + queues ==="
docker exec "$DB" sh -c "mariadb -u drupal -pdrupal_pass drupal -e \"
  DELETE FROM user__roles WHERE entity_id IN ($UID_BZ, $UID_CO);
  DELETE FROM users_data WHERE uid IN ($UID_BZ, $UID_CO);
  DELETE FROM users_field_data WHERE uid IN ($UID_BZ, $UID_CO);
  DELETE FROM users WHERE uid IN ($UID_BZ, $UID_CO);
\"" 2>&1 | tail -1
mq_delete_queue "$QUEUE_REG"
mq_delete_queue "$QUEUE_CO"
echo "  ok  test queues + users cleaned up"
echo ""
echo "✓ All E2E assertions passed."
