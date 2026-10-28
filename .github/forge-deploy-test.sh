set -euo pipefail
cd "$(dirname "$0")/.."
script="$PWD/.github/forge-deploy.sh"
log=$(mktemp)
output=$(mktemp)
trap 'rm -f "$log" "$output"' EXIT
export TEST_LOG="$log"
export FORGE_SITE_PATH="$PWD" FORGE_SITE_BRANCH=main FORGE_PHP=php FORGE_COMPOSER=composer
export TEST_SHA=aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa

git() {
    case "$1" in
        fetch) return 0 ;;
        rev-parse) echo "$TEST_REMOTE" ;;
        merge) echo merge >> "$TEST_LOG" ;;
        *) return 1 ;;
    esac
}
curl() {
    if [[ "$TEST_CHECK" == network-error ]]; then return 22; fi
    if [[ "$TEST_CHECK" == invalid-json ]]; then echo invalid; return; fi
    printf '{"check_runs":[{"app":{"slug":"%s"},"name":"tests","head_sha":"%s","status":"completed","conclusion":"%s"}]}' "$TEST_APP" "$TEST_SHA" "$TEST_CHECK"
}
php() {
    if [[ "$1" == -r ]]; then command php "$@"; else echo artisan >> "$TEST_LOG"; fi
}
composer() { echo composer >> "$TEST_LOG"; }
npm() { echo npm >> "$TEST_LOG"; }
export -f git curl php composer npm

for scenario in missing-commit moved-main failure pending skipped foreign-app network-error invalid-json success; do
    export FORGE_VAR_CI_COMMIT="$TEST_SHA" TEST_REMOTE="$TEST_SHA" TEST_CHECK=success TEST_APP=github-actions
    case "$scenario" in
        missing-commit) export FORGE_VAR_CI_COMMIT='' ;;
        moved-main) export TEST_REMOTE=bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb ;;
        foreign-app) export TEST_APP=other ;;
        success) ;;
        *) export TEST_CHECK="$scenario" ;;
    esac
    : > "$log"
    result=0
    bash "$script" > "$output" 2>&1 || result=$?
    if [[ "$scenario" == success ]]; then
        if [[ "$result" != 0 ]] || ! grep -q composer "$log"; then cat "$output"; exit 1; fi
    else
        if [[ "$result" == 0 ]] || [[ -s "$log" ]]; then echo "Unsafe gate: $scenario"; cat "$output"; exit 1; fi
    fi
    echo "PASS $scenario"
done
