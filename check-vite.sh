#!/bin/bash
SAFE_DIR="$HOME/vitalvida-api/secure-admin-center-33"

echo "🔍 Scanning for stray vite.config.* and .vite caches outside $SAFE_DIR..."
find "$HOME/vitalvida-api" \
  -type f \( -name "vite.config.js" -o -name "vite.config.ts" -o -name "vite.config.mjs" \) \
  ! -path "$SAFE_DIR/*"

find "$HOME/vitalvida-api" \
  -type d -name ".vite" \
  ! -path "$SAFE_DIR/*"

echo "✅ Scan complete. If no results are shown above, you are clear to run 'npm run dev'."
