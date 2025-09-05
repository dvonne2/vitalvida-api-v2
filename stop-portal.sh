#!/usr/bin/env bash
set -eo pipefail
[ $# -gt 0 ] || { echo "Usage: ./stop-portal.sh <portal-name>"; exit 1; }
portal="$1"
get_port() {
  case "$1" in
    logistics) echo 3004 ;;
    inventory-management) echo 3005 ;;
    telesales) echo 3006 ;;
    delivery-agent) echo 3007 ;;
    accountant) echo 3008 ;;
    financial-controller) echo 3009 ;;
    gm) echo 3010 ;;
    ceo) echo 3011 ;;
    hr) echo 3012 ;;
    investor) echo 3013 ;;
    manufacturing) echo 3014 ;;
    media-buyer) echo 3015 ;;
    vitalvida-crm) echo 3016 ;;
    vitalvida-inventory) echo 3017 ;;
    vitalvida-books) echo 3018 ;;
    vitalvida-marketing) echo 3019 ;;
    *) echo ""; return 1 ;;
  esac
}
port="$(get_port "$portal")" || { echo "Unknown portal: $portal"; exit 1; }
pid="$(lsof -tiTCP:$port -sTCP:LISTEN || true)"
[ -n "$pid" ] || { echo "Port $port ($portal) already free."; exit 0; }
echo "Killing $portal on $port (pid $pid)…"
kill -9 $pid || true
echo "Done."
