#!/usr/bin/env bash
# Start All VitalVida Portals (Current Port Configuration)

BASE_DIR="/Users/yesideasekun/vitalvida-api"
cd "$BASE_DIR"

echo "Starting All VitalVida Portals (Current Configuration)"
echo "========================================================"
echo ""

PORTALS=("vitalvida-inventory" "financial-controller" "gm" "delivery-agent" "accountant" "inventory-management" "investor" "ceo" "hr" "logistics" "manufacturing" "media-buyer" "telesales" "vitalvida-books" "vitalvida-crm" "vitalvida-marketing")

mkdir -p logs

for portal in "${PORTALS[@]}"; do
    if [[ -d "$portal" ]]; then
        echo "Starting $portal..."
        cd "$portal"
        nohup ./start-portal.sh > "../logs/${portal}.log" 2>&1 &
        echo "  ✓ $portal started (PID: $!)"
        cd "$BASE_DIR"
        sleep 1
    else
        echo "  ⚠️  Portal $portal not found"
    fi
done

echo ""
echo "All portals started with current port configuration!"
echo ""
echo "Port Assignments (LOCKED - No Port Hopping):"
echo "  vitalvida-inventory   → http://localhost:3004"
echo "  financial-controller  → http://localhost:3005"
echo "  gm                    → http://localhost:3006"
echo "  delivery-agent        → http://localhost:3007"
echo "  accountant            → http://localhost:3008"
echo "  inventory-management  → http://localhost:3009"
echo "  investor              → http://localhost:3010"
echo "  ceo                   → http://localhost:3011"
echo "  hr                    → http://localhost:3012"
echo "  logistics             → http://localhost:3013"
echo "  manufacturing         → http://localhost:3014"
echo "  media-buyer           → http://localhost:3015"
echo "  telesales             → http://localhost:3016"
echo "  vitalvida-books       → http://localhost:3017"
echo "  vitalvida-crm         → http://localhost:3018"
echo "  vitalvida-marketing   → http://localhost:3019"
echo ""
echo "Health Checks:"
echo "  curl http://localhost:3008/health.json  # accountant"
echo "  curl http://localhost:3016/health.json  # telesales"
echo ""
echo "View Logs:"
echo "  tail -f logs/accountant.log"
echo "  tail -f logs/telesales.log"
echo ""
echo "Admin Center: http://localhost:8080"
