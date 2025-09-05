#!/usr/bin/env bash
set -eo pipefail

ports="3004 logistics
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

printf "%-6s  %-24s  %s\n" "PORT" "PORTAL" "STATUS"
echo "---------------------------------------------------------"
while read -r p name; do
  [ -z "$p" ] && continue
  if lsof -tiTCP:$p -sTCP:LISTEN >/dev/null 2>&1; then
    printf "%-6s  %-24s  UP\n" "$p" "$name"
  else
    printf "%-6s  %-24s  DOWN\n" "$p" "$name"
  fi
done <<< "$ports"
