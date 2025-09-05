#!/usr/bin/env bash
set -eo pipefail
BASE="/Users/yesideasekun/vitalvida-api"

rows="3004 logistics
3005 inventory-management
3006 telesales
3007 delivery-agent
3008 accountant
3009 financial-controller
3010 gm
3011 ceo
3012 hr
3013 investor
3014 manufacturing
3015 media-buyer
3016 vitalvida-crm
3017 vitalvida-inventory
3018 vitalvida-books
3019 vitalvida-marketing"

while read -r port name; do
  [ -z "$port" ] && continue
  if lsof -tiTCP:$port -sTCP:LISTEN >/dev/null 2>&1; then
    echo "✔ $name already UP on $port"
  else
    echo "▶ Starting $name on $port…"
    ( cd "$BASE" && ./start-portal.sh "$name" ) >"$BASE/$name.dev.log" 2>&1 &
    sleep 0.7
  fi
done <<< "$rows"

echo "Use: tail -f $BASE/<portal>.dev.log"
