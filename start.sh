#!/bin/sh
# Start the signing service and PHP dev server on the first free ports.
APP_PORT=${APP_PORT:-18180}
A_BOGUS_PORT=${A_BOGUS_PORT:-9876}
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"

port_in_use() {
  if command -v nc >/dev/null 2>&1; then
    nc -z 127.0.0.1 "$1" >/dev/null 2>&1
  else
    (echo >/dev/tcp/127.0.0.1/"$1") >/dev/null 2>&1
  fi
}

while port_in_use "$APP_PORT"; do APP_PORT=$((APP_PORT + 1)); done
while port_in_use "$A_BOGUS_PORT"; do A_BOGUS_PORT=$((A_BOGUS_PORT + 1)); done

A_BOGUS_PORT="$A_BOGUS_PORT" nohup node "$SCRIPT_DIR/scripts/a_bogus_server.js" > /dev/null 2>&1 &
A_BOGUS_PID=$!
echo "a_bogus server started (PID: $A_BOGUS_PID, port: $A_BOGUS_PORT)"
echo "PHP server starting on http://127.0.0.1:$APP_PORT"

# Trap to clean up on exit
trap "kill $A_BOGUS_PID 2>/dev/null; exit" INT TERM EXIT

# php -S 默认单 worker，一次慢解析会阻塞全部请求
PHP_CLI_SERVER_WORKERS="${PHP_CLI_SERVER_WORKERS:-8}"
export PHP_CLI_SERVER_WORKERS

A_BOGUS_PORT="$A_BOGUS_PORT" php -S 127.0.0.1:"$APP_PORT" -t "$SCRIPT_DIR/public" "$SCRIPT_DIR/public/router.php"
