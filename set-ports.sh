#!/bin/bash
set -e

BASE="/Users/yesideasekun/vitalvida-api"

# folder  port
PORTS=$(cat <<'EOF'
delivery-agent 3007
accountant 3008
financial-controller 3009
gm 3010
ceo 3011
hr 3012
investor 3013
manufacturing 3014
media-buyer 3015
vitalvida-crm 3016
vitalvida-inventory 3017
vitalvida-books 3018
vitalvida-marketing 3019
EOF
)

echo "🔧 Setting fixed ports in vite.config.ts …"

echo "$PORTS" | while read -r dir port; do
  [ -z "$dir" ] && continue
  if [ ! -d "$BASE/$dir" ]; then
    echo "❌ Missing: $dir (skip)"
    continue
  fi

  cd "$BASE/$dir"

  # Create a minimal config if missing
  if [ ! -f vite.config.ts ]; then
    echo "📝 Creating vite.config.ts in $dir"
    cat > vite.config.ts <<EOF2
import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig({
  plugins: [react()],
  server: { port: $port, strictPort: true, host: true }
})
EOF2
    echo "✅ $dir → $port (created)"
    continue
  fi

  # Backup existing config
  cp -f vite.config.ts vite.config.ts.bak 2>/dev/null || true

  # If it already has a port, replace it (macOS sed needs '' after -i)
  if grep -qE "port:\s*[0-9]+" vite.config.ts; then
    sed -i '' -E "s/(port:\s*)[0-9]+/\1$port/" vite.config.ts
    # Ensure strictPort + host exist
    grep -q "strictPort:" vite.config.ts || sed -i '' -E "s/server:\s*\{([^}]*)\}/server: { \1, strictPort: true }/" vite.config.ts
    grep -q "host:" vite.config.ts || sed -i '' -E "s/server:\s*\{([^}]*)\}/server: { \1, host: true }/" vite.config.ts
    echo "✅ $dir → $port (replaced)"
  else
    # Insert a server block right after export default defineConfig({
    awk -v p="$port" '
      BEGIN{ins=0}
      /export default defineConfig\(\{/ && !ins {
        print;
        print "  server: { port: " p ", strictPort: true, host: true },";
        ins=1; next
      }
      {print}
      END{
        if(!ins){
          print "\nexport default defineConfig({ server: { port: " p ", strictPort: true, host: true } });"
        }
      }
    ' vite.config.ts > vite.config.ts.new && mv vite.config.ts.new vite.config.ts
    echo "✅ $dir → $port (inserted)"
  fi
done

echo "🎉 Done."
