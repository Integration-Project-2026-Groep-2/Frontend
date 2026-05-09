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

JAR=$(mktemp)
trap "rm -f $JAR" EXIT

EMAIL_BZ="e2e-bz-$(date +%s)@local.test"
EMAIL_CO="e2e-co-$(date +%s)@local.test"
PASS_BZ="bezoeker1234"
PASS_CO="bedrijf1234567"

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
DATA="registratie_type=bedrijf&firstName=&lastName=&email=$EMAIL_CO&phone=&role=&companyName=E2E+BV&vatNumber=BE0888777666&street=Stationsstraat+1&city=Brussel&gdpr_consent=1&pass[pass1]=$PASS_CO&pass[pass2]=$PASS_CO&op=Registreer!&form_build_id=$BUILD_ID&form_token=$TOKEN&form_id=shift_bezoeker_registratie_form"
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
echo "=== Cleanup test users ==="
docker exec "$DB" sh -c "mariadb -u drupal -pdrupal_pass drupal -e \"
  DELETE FROM user__roles WHERE entity_id IN ($UID_BZ, $UID_CO);
  DELETE FROM users_data WHERE uid IN ($UID_BZ, $UID_CO);
  DELETE FROM users_field_data WHERE uid IN ($UID_BZ, $UID_CO);
  DELETE FROM users WHERE uid IN ($UID_BZ, $UID_CO);
\"" 2>&1 | tail -1
echo ""
echo "✓ All E2E assertions passed."
