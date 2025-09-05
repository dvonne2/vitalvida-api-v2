#!/bin/bash
# Start a specific VitalVida portal

BASE_DIR="$HOME/vitalvida-api"

if [ $# -eq 0 ]; then
  echo "Usage: ./start-portal.sh <portal-name>"
  echo ""
  echo "Available portals:"
  echo "  logistics, inventory-management, telesales, delivery-agent"
  echo "  accountant, financial-controller, gm, ceo, hr, investor"
  echo "  manufacturing, media-buyer, vitalvida-crm, vitalvida-inventory"
  echo "  vitalvida-books, vitalvida-marketing"
  exit 1
fi

PORTAL=$1
PORTAL_DIR="$BASE_DIR/$PORTAL"

if [ ! -d "$PORTAL_DIR" ]; then
  echo "❌ Portal not found: $PORTAL_DIR"
  exit 1
fi

echo "🚀 Starting $PORTAL portal..."
cd "$PORTAL_DIR"
npm install && npm run dev
