set -euo pipefail

cd "$FORGE_SITE_PATH"

# A commit label alone does not pin a Forge deployment.
tested_commit="${FORGE_VAR_CI_COMMIT:-}"
if [[ ! "$tested_commit" =~ ^[0-9a-f]{40}$ ]]; then
    echo "Deployment blocked: a tested commit is required."
    exit 1
fi

git fetch origin "$FORGE_SITE_BRANCH"
if [[ "$(git rev-parse FETCH_HEAD)" != "$tested_commit" ]]; then
    echo "Deployment blocked: main has moved since this CI run."
    exit 1
fi

curl --fail --silent --show-error --max-time 30 \
    -H 'Accept: application/vnd.github+json' \
    "https://api.github.com/repos/trr-code/supportflow-ai/commits/$tested_commit/check-runs?check_name=tests&filter=latest" \
    | "$FORGE_PHP" -r '
        $data = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
        foreach ($data["check_runs"] ?? [] as $check) {
            if (($check["app"]["slug"] ?? "") === "github-actions"
                && $check["name"] === "tests"
                && $check["head_sha"] === $argv[1]
                && $check["status"] === "completed"
                && $check["conclusion"] === "success") {
                exit(0);
            }
        }
        fwrite(STDERR, "Deployment blocked: successful CI is required for this commit.\n");
        exit(1);
    ' "$tested_commit"

git merge --ff-only "$tested_commit"
if [[ "$(git rev-parse HEAD)" != "$tested_commit" ]]; then
    echo "Deployment blocked: working tree does not match the tested commit."
    exit 1
fi

$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader
npm ci
npm run build
$FORGE_PHP artisan migrate --force
$FORGE_PHP artisan optimize
$FORGE_PHP artisan octane:reload
$FORGE_PHP artisan queue:restart
