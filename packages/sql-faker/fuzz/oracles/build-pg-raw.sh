#!/bin/sh
set -eu
sqlfaker_pg_config=${SQLFAKER_PG_CONFIG:-pg_config}
case "$("$sqlfaker_pg_config" --version)" in
  'PostgreSQL 17.2'*) ;;
  *) echo 'PostgreSQL 17.2 development headers are required.' >&2; exit 2 ;;
esac
sqlfaker_output=${1:?Usage: build-pg-raw.sh /path/to/sqlfaker_raw_parse.so}
cc -shared -fPIC -O2 -I"$("$sqlfaker_pg_config" --includedir-server)" \
  "$(dirname "$0")/sqlfaker_raw_parse.c" -o "$sqlfaker_output"
