#!/usr/bin/env bash
set -Eeuo pipefail
umask 077

fail() {
    printf 'Homepage publishing stopped: %s\n' "$*" >&2
    exit 1
}

: "${HOMEPAGE_SSH_HOST:?Set the HOMEPAGE_SSH_HOST repository variable.}"
: "${HOMEPAGE_SSH_PORT:?Set the HOMEPAGE_SSH_PORT repository variable.}"
: "${HOMEPAGE_SSH_USER:?Set the HOMEPAGE_SSH_USER repository variable.}"
: "${HOMEPAGE_DIRECTORY:?Set the HOMEPAGE_DIRECTORY repository variable.}"
: "${HOMEPAGE_SSH_KEY:?Set the HOMEPAGE_SSH_KEY repository secret.}"
: "${HOMEPAGE_KNOWN_HOSTS:?Set the HOMEPAGE_KNOWN_HOSTS repository secret.}"

[[ "$HOMEPAGE_SSH_HOST" =~ ^[A-Za-z0-9.-]+$ && "$HOMEPAGE_SSH_HOST" != -* ]] || fail 'Invalid SSH host.'
[[ "$HOMEPAGE_SSH_USER" =~ ^[A-Za-z0-9_][A-Za-z0-9_-]*$ ]] || fail 'Invalid SSH username.'
[[ "$HOMEPAGE_SSH_PORT" =~ ^[0-9]{1,5}$ ]] || fail 'Invalid SSH port.'
(( 10#$HOMEPAGE_SSH_PORT >= 1 && 10#$HOMEPAGE_SSH_PORT <= 65535 )) || fail 'SSH port is out of range.'
[[ "$HOMEPAGE_DIRECTORY" =~ ^/[A-Za-z0-9_./-]+/public_html$ ]] || fail 'The destination must be the absolute public_html directory.'
case "$HOMEPAGE_DIRECTORY/" in
    *'/../'*|*'/./'*|*'//'*) fail 'The destination must be a plain directory path.' ;;
esac
case "${HOMEPAGE_DRY_RUN:-true}" in
    true|false) ;;
    *) fail 'HOMEPAGE_DRY_RUN must be true or false.' ;;
esac

homepage_repo=$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd -P)
cd -- "$homepage_repo"
[[ -d site && ! -L site ]] || fail 'Missing regular site directory.'
[[ -f site/index.html && ! -L site/index.html ]] || fail 'Missing regular site/index.html file.'
for homepage_assets in css image; do
    [[ -d "site/$homepage_assets" && ! -L "site/$homepage_assets" ]] || fail "Missing regular site/$homepage_assets directory."
    [[ -z "$(find "site/$homepage_assets" -type l -print -quit)" ]] || fail 'Source assets must not contain symbolic links.'
done

homepage_ssh_dir=$(mktemp -d "${RUNNER_TEMP:-${TMPDIR:-/tmp}}/homepage-ssh.XXXXXX")
cleanup() {
    rm -f -- "$homepage_ssh_dir/key" "$homepage_ssh_dir/known_hosts" "$homepage_ssh_dir/config"
    rmdir -- "$homepage_ssh_dir"
}
trap cleanup EXIT
printf '%s\n' "$HOMEPAGE_SSH_KEY" | tr -d '\r' > "$homepage_ssh_dir/key"
printf '%s\n' "$HOMEPAGE_KNOWN_HOSTS" | tr -d '\r' > "$homepage_ssh_dir/known_hosts"
chmod 600 "$homepage_ssh_dir/key" "$homepage_ssh_dir/known_hosts"
cat > "$homepage_ssh_dir/config" <<EOF
Host homepage-site
    HostName $HOMEPAGE_SSH_HOST
    Port $HOMEPAGE_SSH_PORT
    User $HOMEPAGE_SSH_USER
    IdentityFile "$homepage_ssh_dir/key"
    UserKnownHostsFile "$homepage_ssh_dir/known_hosts"
    IdentitiesOnly yes
    StrictHostKeyChecking yes
    BatchMode yes
    PasswordAuthentication no
    KbdInteractiveAuthentication no
    ConnectTimeout 15
    LogLevel ERROR
EOF

# Verify the existing target without creating or changing any other folder.
ssh -F "$homepage_ssh_dir/config" homepage-site \
    "test -d '$HOMEPAGE_DIRECTORY' && test ! -L '$HOMEPAGE_DIRECTORY' && test ! -L '$HOMEPAGE_DIRECTORY/css' && test ! -L '$HOMEPAGE_DIRECTORY/image' && command -v rsync >/dev/null"

homepage_rsync_options=(--recursive --links --times --compress --checksum --safe-links --delay-updates --itemize-changes --protect-args --chmod=Du=rwx,Dgo=rx,Fu=rw,Fgo=r)
if [[ "${HOMEPAGE_DRY_RUN:-true}" == true ]]; then
    homepage_rsync_options+=(--dry-run)
    printf 'Previewing homepage uploads; no server files will change.\n'
fi

# Only these named public paths are sent. Repository files and other server
# folders are outside the upload scope.
# Assets arrive first; HTML is sent only after the asset transfer succeeds.
# No --delete option is used, so older assets remain available.
rsync "${homepage_rsync_options[@]}" -e "ssh -F '$homepage_ssh_dir/config'" \
    -- site/css site/image "homepage-site:$HOMEPAGE_DIRECTORY/"
rsync "${homepage_rsync_options[@]}" -e "ssh -F '$homepage_ssh_dir/config'" \
    -- site/index.html "homepage-site:$HOMEPAGE_DIRECTORY/"

printf 'Homepage upload finished (dry run: %s).\n' "${HOMEPAGE_DRY_RUN:-true}"
