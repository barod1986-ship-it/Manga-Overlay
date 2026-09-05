#!/usr/bin/env bash
set -euo pipefail

MOL_SCOPE="${1:-all}"

fail() {
  printf 'ERROR: %s\n' "$1" >&2
  exit 1
}

require_command() {
  command -v "$1" >/dev/null 2>&1 || fail "Missing required command: $1"
}

check_frontend() {
  require_command node
  require_command npm

  local node_major
  node_major="$(node --version | sed -E 's/^v([0-9]+).*/\1/')"
  [[ "$node_major" == "24" ]] || fail "Node 24 is required; found $(node --version)"
  printf 'Node: %s\n' "$(node --version)"
  printf 'npm: %s\n' "$(npm --version)"
}

check_backend() {
  require_command php
  require_command composer

  local php_minor
  php_minor="$(php -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;')"
  [[ "$php_minor" == "8.4" ]] || fail "PHP 8.4 is required; found $(php -r 'echo PHP_VERSION;')"
  printf 'PHP: %s\n' "$(php -r 'echo PHP_VERSION;')"
  printf 'Composer: %s\n' "$(composer --version --no-ansi)"
}

case "$MOL_SCOPE" in
  frontend)
    check_frontend
    ;;
  backend)
    check_backend
    ;;
  all)
    check_frontend
    check_backend
    ;;
  *)
    fail "Usage: scripts/check-environment.sh [frontend|backend|all]"
    ;;
esac
