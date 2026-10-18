#!/bin/bash
# Two-site LTI 1.3 environment for the FlexAccess cross-site suite (CI and local, same script).
#
# Builds a platform (consumer) and a provider (tool) Moodle site from one Moodle checkout, installs the
# FlexAccess plugins (and tool_mergeusers on the provider, if given), seeds the LTI 1.3 relationship in
# both directions and starts both sites behind a TLS front:
#   https://platform.test:8443  -> php -S 127.0.0.1:8101
#   https://provider.test:8444  -> php -S 127.0.0.1:8102
# The hostnames must resolve to 127.0.0.1 and the test CA must be trusted by PHP's curl (see the
# workflow). Writes the seeded values to $WORK/lti.env (shell "export" lines).
#
# Required environment:
#   MOODLE_SRC   Moodle checkout (4.5: plain layout, 5.1+: public/ layout)
#   PLUGINS_DIR  directory containing moodle-{auth,enrol,mod,tool}_flexaccess
#   WORK         working directory (sites, dataroots, logs)
#   CERTS        directory with platform.{crt,key} and provider.{crt,key}
#   DBTYPE DBHOST DBPORT DBUSER DBPASS  database server (databases lti_platform and lti_provider are
#                                       created by the caller)
# Optional:
#   MERGEUSERS_DIR  tool_mergeusers checkout, installed on the provider
set -euo pipefail

: "${MOODLE_SRC:?}" "${PLUGINS_DIR:?}" "${WORK:?}" "${CERTS:?}"
: "${DBTYPE:=pgsql}" "${DBHOST:=127.0.0.1}" "${DBPORT:=5432}" "${DBUSER:=moodle}" "${DBPASS:=moodle}"
HERE="$(cd "$(dirname "$0")" && pwd)"
mkdir -p "$WORK"

PUBLIC=""
if [ -d "$MOODLE_SRC/public" ]; then
  PUBLIC="public/"
fi

build_site () {
  local name="$1" port="$2" db="$3"
  local root="$WORK/$name" data="$WORK/${name}data"
  rm -rf "$root" "$data"
  mkdir -p "$root" "$data"
  # vendor/ (Composer, several GB with the development tools) is shared with the checkout, not copied.
  tar -C "$MOODLE_SRC" --exclude=./.git --exclude=./config.php --exclude=./vendor -cf - . | tar -C "$root" -xf -
  if [ -d "$MOODLE_SRC/vendor" ]; then
    ln -s "$MOODLE_SRC/vendor" "$root/vendor"
  fi
  declare -A target=([auth]=auth/flexaccess [enrol]=enrol/flexaccess [mod]=mod/flexaccess [tool]=admin/tool/flexaccess)
  for p in auth enrol mod tool; do
    local dest="$root/$PUBLIC${target[$p]}"
    rm -rf "$dest"; mkdir -p "$dest"
    tar -C "$PLUGINS_DIR/moodle-${p}_flexaccess" --exclude=.git --exclude=node_modules -cf - . | tar -C "$dest" -xf -
  done
  if [ "$name" = provider ] && [ -n "${MERGEUSERS_DIR:-}" ]; then
    local dest="$root/${PUBLIC}admin/tool/mergeusers"
    rm -rf "$dest"; mkdir -p "$dest"
    tar -C "$MERGEUSERS_DIR" --exclude=.git -cf - . | tar -C "$dest" -xf -
  fi
  (cd "$root" && php admin/cli/install.php --non-interactive --agree-license \
      --dbtype="$DBTYPE" --dbhost="$DBHOST" --dbport="$DBPORT" --dbname="$db" --dbuser="$DBUSER" --dbpass="$DBPASS" \
      --prefix=mdl_ --wwwroot="https://$name.test:$port" --dataroot="$data" \
      --fullname="LTI $name" --shortname="$name" --adminpass='Admin!23' --adminemail="admin@$name.test" \
      > "$WORK/$name-install.log" 2>&1) || { tail -30 "$WORK/$name-install.log"; exit 1; }
  # Served over HTTPS by the TLS front; server-to-server calls (JWKS, AGS, NRPS, token) go to the
  # other local site, which Moodle's default curl security would block.
  php -r '
    $file = $argv[1];
    $config = file_get_contents($file);
    $extra = "\$CFG->sslproxy = true;\n\$CFG->reverseproxy = true;\n\$CFG->curlsecurityblockedhosts = \"\";\n\$CFG->curlsecurityallowedport = \"\";\n"
        . "\$CFG->cookiesecure = true;\n\$CFG->sessioncookie = \"" . $argv[2] . "\";\n";
    $config = str_replace("require_once(__DIR__ . \x27/lib/setup.php\x27);", $extra . "require_once(__DIR__ . \x27/lib/setup.php\x27);", $config);
    file_put_contents($file, $config);
  ' "$root/config.php" "$name"
  grep -q 'sslproxy' "$root/config.php" || { echo "config.php of $name was not extended"; exit 1; }
}

build_site provider 8444 lti_provider
build_site platform 8443 lti_platform

seed () {
  local site="$1" script="$2"
  (cd "$WORK/$site" && php "${PUBLIC}enrol/flexaccess/tests/playwright/lti/$script")
}
seed provider seed-provider.php > "$WORK/lti.env"
set -a; . "$WORK/lti.env"; set +a
seed platform seed-platform.php >> "$WORK/lti.env"
set -a; . "$WORK/lti.env"; set +a
seed provider seed-registration.php >> "$WORK/lti.env"
{
  echo "export LTI_PLATFORM_ROOT='$WORK/platform'"
  echo "export LTI_PROVIDER_ROOT='$WORK/provider'"
  echo "export LTI_CLI='${PUBLIC}enrol/flexaccess/tests/playwright/lti/cli.php'"
} >> "$WORK/lti.env"

start_site () {
  local name="$1" php="$2" tls="$3"
  # The proxies of the build environment must not capture the sites' server-to-server calls.
  env -u https_proxy -u HTTPS_PROXY -u http_proxy -u HTTP_PROXY NO_PROXY='*' \
    PHP_CLI_SERVER_WORKERS=8 nohup php -S "127.0.0.1:$php" -t "$WORK/$name/$PUBLIC" \
    > "$WORK/$name-server.log" 2>&1 &
  nohup node "$HERE/tls-proxy.js" "$tls" "$php" "$CERTS/$name.crt" "$CERTS/$name.key" > "$WORK/$name-tls.log" 2>&1 &
}
start_site provider 8102 8444
start_site platform 8101 8443

for site in "platform.test:8443" "provider.test:8444"; do
  ready=0
  for i in $(seq 1 40); do
    code=$(curl --noproxy '*' -s -o /dev/null -w '%{http_code}' --max-time 20 "https://$site/login/index.php" || true)
    if [ "$code" = "200" ]; then ready=1; break; fi
    sleep 2
  done
  if [ "$ready" != "1" ]; then
    echo "https://$site not reachable (last status $code)"
    tail -30 "$WORK"/*-server.log
    exit 1
  fi
done
echo "Both LTI sites are up; values in $WORK/lti.env"
