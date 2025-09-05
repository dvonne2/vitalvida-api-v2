#!/usr/bin/env bash
BASE="/Users/yesideasekun/vitalvida-api"
APPS=(
  "logistics" "inventory-management" "telesales" "delivery-agent" "accountant" "financial-controller"
  "gm" "ceo" "hr" "investor" "manufacturing" "media-buyer" "vitalvida-crm" "vitalvida-inventory" "vitalvida-books" "vitalvida-marketing"
)

osascript <<'AS'
tell application "Terminal"
  activate
end tell
AS

for d in "${APPS[@]}"; do
osascript <<AS
tell application "Terminal"
  do script "cd \"$BASE/$d\" && npm install && (npm run dev || npm run dev:fixed)"
end tell
AS
sleep 0.5
done
