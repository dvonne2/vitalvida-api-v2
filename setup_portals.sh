#!/usr/bin/env bash
set -euo pipefail

# Configuration list (compatible with macOS bash 3.2):
# "dir|Display Name|PORT" per line
PORTALS=$(cat <<'EOF'
vitalvida-inventory|VitalVida Inventory|3004
financial-controller|Financial Controller|3005
gm|General Manager|3006
delivery-agent|Delivery Agent|3007
accountant|Accountant|3008
inventory-management|Inventory Management|3009
investor|Investor|3010
ceo|CEO|3011
hr|HR|3012
logistics|Logistics|3013
manufacturing|Manufacturing|3014
media-buyer|Media Buyer|3015
telesales|Telesales|3016
vitalvida-books|VitalVida Books|3017
vitalvida-crm|VitalVida CRM|3018
vitalvida-marketing|VitalVida Marketing|3019
EOF
)

ALLOWED_IFRAME_ORIGINS_STR="http://localhost:8080 http://127.0.0.1:8080"

root_dir="$(cd "$(dirname "$0")" && pwd)"
cd "$root_dir"

printf "\nConfiguring portals with immutable ports and health endpoints...\n\n"

summary=""

while IFS='|' read -r dir display_name port; do
  [[ -z "${dir:-}" ]] && continue
  portal_dir="$root_dir/$dir"

  if [[ ! -d "$portal_dir" ]]; then
    printf "[SKIP] %s (missing directory)\n" "$dir"
    continue
  fi

  # Ensure public dir exists
  mkdir -p "$portal_dir/public"

  # 1) .env
  if [[ -f "$portal_dir/.env" ]]; then chmod u+w "$portal_dir/.env" || true; fi
  cat >"$portal_dir/.env" <<EOF
VITE_PORT=$port
VITE_ADMIN_ALLOWED_IFRAME_ORIGINS=${ALLOWED_IFRAME_ORIGINS_STR}
VITE_PUBLIC_HEALTH_PATH=/health.json
EOF

  # 2) port.config.js (ESM)
  if [[ -f "$portal_dir/port.config.js" ]]; then chmod u+w "$portal_dir/port.config.js" || true; fi
  cat >"$portal_dir/port.config.js" <<EOF
export const PORT = $port;
export const ALLOWED_IFRAME_ORIGINS = Object.freeze([
  'http://localhost:8080',
  'http://127.0.0.1:8080'
]);
export default Object.freeze({ PORT, ALLOWED_IFRAME_ORIGINS });
EOF

  # 3) start-portal.sh
  cat >"$portal_dir/start-portal.sh" <<'EOS'
#!/usr/bin/env bash
set -euo pipefail
DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$DIR"

if [[ ! -f .env ]]; then
  echo "Error: .env not found" >&2
  exit 1
fi

PORT=$(grep -E '^VITE_PORT=' .env | cut -d= -f2 || true)
if [[ -z "${PORT:-}" ]]; then
  echo "Error: VITE_PORT missing in .env" >&2
  exit 1
fi

if command -v bun >/dev/null 2>&1; then
  bun run dev --port "$PORT" --strictPort --host
else
  npm run dev -- --port "$PORT" --strictPort --host
fi
EOS
  chmod +x "$portal_dir/start-portal.sh"

  # 4) public/health.json
  cat >"$portal_dir/public/health.json" <<EOF
{
  "name": "$display_name Portal",
  "port": $port,
  "status": "ok",
  "version": "dev",
  "uptimeSeconds": 0
}
EOF

  # Lock key config files as immutable (read-only)
  chmod 444 "$portal_dir/.env" "$portal_dir/port.config.js" || true

  summary+="$display_name|$dir|$port\n"
  printf "[OK] %-22s dir=%-22s port=%s\n" "$display_name" "$dir" "$port"
done <<< "$PORTALS"

printf "\nSummary (Name | Directory | Port)\n"
printf -- "---------------------------------------------\n"
printf "%b" "$summary" | while IFS='|' read -r n d p; do
  [[ -z "${n:-}" ]] && continue
  printf "%s | %s | %s\n" "$n" "$d" "$p"
done

printf "\nDone. Use each portal's start-portal.sh to launch.\n\n"


