#!/usr/bin/env bash
set -Eeuo pipefail
umask 077

: "${HOMEPAGE_SSH_KEY:?Missing deployment key}"
: "${HOMEPAGE_KNOWN_HOSTS:?Missing hosting identity}"
[[ "${HOMEPAGE_SSH_HOST:-}" =~ ^[A-Za-z0-9][A-Za-z0-9.-]*$ ]] || exit 1
[[ "${HOMEPAGE_SSH_USER:-}" =~ ^[A-Za-z0-9_][A-Za-z0-9_-]*$ ]] || exit 1
[[ "${HOMEPAGE_SSH_PORT:-}" =~ ^[0-9]{1,5}$ ]] || exit 1
[[ "${HOMEPAGE_DIRECTORY:-}" =~ ^(/[A-Za-z0-9_-]+)+/public_html$ ]] || exit 1

connection_files=$(mktemp -d)
trap 'rm -f -- "$connection_files/key" "$connection_files/known_hosts"; rmdir -- "$connection_files"' EXIT
printf '%s\n' "$HOMEPAGE_SSH_KEY" | tr -d '\r' > "$connection_files/key"
printf '%s\n' "$HOMEPAGE_KNOWN_HOSTS" | tr -d '\r' > "$connection_files/known_hosts"

read -r -d '' deploy_remote <<'REMOTE' || true
set -eu
document_root=$1
preview="$document_root/project-preview"
data_dir="${document_root%/public_html}/project-preview-data"
test "$(realpath "$document_root")" = "$document_root"
test -d "$preview" && test ! -L "$preview" && test -f "$preview/.htaccess"
test ! -L "$data_dir"

umask 077
staging=$(mktemp -d "${document_root%/public_html}/.project-release.XXXXXX")
trap 'rm -r -- "$staging"' EXIT
tar --extract --file=- --no-same-owner --no-same-permissions -C "$staging"

python3 "$staging/scripts/init-project-databases.py" --data-dir "$data_dir"
mkdir -p "$preview/lexicon/files" "$preview/media/storage/files" "$preview/media/storage/tmpfiles" \
         "$preview/patients/files" "$preview/patients-legacy/files"

rsync --recursive --checksum --delay-updates --itemize-changes \
    --chmod=Du=rwx,Dgo=rx,Fu=rw,Fgo=r \
    --exclude='.*' --exclude='README.md' --exclude='files/' --exclude='storage/' \
    "$staging/apps/" "$preview/"
REMOTE

remote_command=$(printf 'bash -c %q -- %q' "$deploy_remote" "$HOMEPAGE_DIRECTORY")
# The archive is generated from the commit. Ignored databases and attachments stay off the runner.
git archive HEAD apps schemas scripts/init-project-databases.py |
    ssh -p "$HOMEPAGE_SSH_PORT" -i "$connection_files/key" \
        -o IdentitiesOnly=yes -o StrictHostKeyChecking=yes -o BatchMode=yes \
        -o "UserKnownHostsFile=$connection_files/known_hosts" -o ConnectTimeout=15 \
        "$HOMEPAGE_SSH_USER@$HOMEPAGE_SSH_HOST" "$remote_command"
