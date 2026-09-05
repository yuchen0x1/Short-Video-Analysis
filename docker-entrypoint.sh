#!/bin/sh
set -e

# 抖音解析依赖本地 a_bogus 签名服务（DouyinParser::generateABogus 只走 HTTP 签名）
A_BOGUS_PORT="${A_BOGUS_PORT:-9876}" node /app/scripts/a_bogus_server.js &

exec "$@"
