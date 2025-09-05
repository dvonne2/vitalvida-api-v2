#!/bin/bash
set -euo pipefail

BASE_DIR="$HOME/vitalvida-api"
mkdir -p "$BASE_DIR"
cd "$BASE_DIR"

# Free the ports first
for port in {3004..3019}; do
  lsof -ti:$port | xargs kill -9 2>/dev/null || true
_done=true
done

echo "🔄 Cloning Role-Based Portals..."
[ ! -d "logistics" ]              && git clone https://github.com/dvonne2/vitalvida-accountability-portal logistics
[ ! -d "inventory-management" ]   && git clone https://github.com/dvonne2/vitalvida-inventory-guardian inventory-management
[ ! -d "telesales" ]              && git clone https://github.com/dvonne2/vitalvida-telesales telesales
[ ! -d "delivery-agent" ]         && git clone https://github.com/dvonne2/delivery-agent-logistics-hub delivery-agent
[ ! -d "accountant" ]             && git clone https://github.com/dvonne2/vitalvida-compliance-guard accountant
[ ! -d "financial-controller" ]   && git clone https://github.com/dvonne2/first-bank-fin-control financial-controller
[ ! -d "gm" ]                     && git clone https://github.com/dvonne2/general-manager gm
[ ! -d "ceo" ]                    && git clone https://github.com/dvonne2/vitalvida-ceo-center-africa ceo
[ ! -d "hr" ]                     && git clone https://github.com/dvonne2/vitalvida-zenith-hr hr
[ ! -d "investor" ]               && git clone https://github.com/dvonne2/vitalvida-investor-cockpit investor
[ ! -d "manufacturing" ]          && git clone https://github.com/dvonne2/vitalvida-cost-brain manufacturing
[ ! -d "media-buyer" ]            && git clone https://github.com/dvonne2/nigeria-market-pulse media-buyer

echo "🔄 Cloning System Portals..."
[ ! -d "vitalvida-crm" ]          && git clone https://github.com/dvonne2/kemi-flow-ai vitalvida-crm
[ ! -d "vitalvida-inventory" ]    && git clone https://github.com/dvonne2/vitalvida-agent-suite vitalvida-inventory
[ ! -d "vitalvida-books" ]        && git clone https://github.com/dvonne2/vitalvida-joy-books vitalvida-books
[ ! -d "vitalvida-marketing" ]    && git clone https://github.com/dvonne2/vital-brain-ng vitalvida-marketing

lock_portal() {
  local dir="$1"
  local port="$2"
  local vite="$BASE_DIR/$dir/vite.config.ts"
  mkdir -p "$BASE_DIR/$dir"
  # ensure writable if file already exists
  [ -f "$vite" ] && chmod u+w "$vite" 2>/dev/null || true
  cat > "$vite" <<EOT
import { defineConfig } from vite
import react from @vitejs/plugin-react
import path from path
export default defineConfig({
  plugins: [react()],
  resolve: { alias: { @: path.resolve(__dirname, ./src) } },
  server: { port: $port, strictPort: true, host: true, open: false }
})
EOT
  cd "$BASE_DIR/$dir"
  npm pkg set scripts.dev="vite --port $port --strictPort --host" >/dev/null
  npm pkg set scripts.start="vite --port $port --strictPort --host" >/dev/null
  chmod 444 "$vite"
  echo "✅ $dir locked to $port"
}

lock_portal logistics              3004
lock_portal inventory-management   3005
lock_portal telesales              3006
lock_portal delivery-agent         3007
lock_portal accountant             3008
lock_portal financial-controller   3009
lock_portal gm                     3010
lock_portal ceo                    3011
lock_portal hr                     3012
lock_portal investor               3013
lock_portal manufacturing          3014
lock_portal media-buyer            3015
lock_portal vitalvida-crm          3016
lock_portal vitalvida-inventory    3017
lock_portal vitalvida-books        3018
lock_portal vitalvida-marketing    3019

echo "�� Phase 1 complete."
