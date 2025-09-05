#!/usr/bin/env bash
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

to_start=()
while read -r port name; do
  [ -z "$port" ] && continue
  if lsof -tiTCP:$port -sTCP:LISTEN >/dev/null 2>&1; then
    echo "✔ $name already UP on $port"
  else
    to_start+=("$name")
  fi
done <<< "$rows"

if [ ${#to_start[@]} -eq 0 ]; then
  echo "All portals already running."
  exit 0
fi

/usr/bin/osascript <<AS
set base to "$BASE"
set apps to {$(printf "\"%s\"," "${to_start[@]}" | sed 's/,$//')}
tell application "Terminal"
  activate
  if (count of windows) = 0 then reopen
  set w to front window
  repeat with d in apps
    do script "cd " & base & " && ./start-portal.sh " & d in w
    delay 0.7
  end repeat
end tell
AS
