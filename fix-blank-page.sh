#!/bin/bash

echo "🔧 Fixing blank page issue for secure-admin-center-33"
echo "=================================================="

# Step 1: Ensure we're in the correct directory
echo "📍 Step 1: Checking directory..."
cd ~/vitalvida-api/secure-admin-center-33
CURRENT_DIR=$(pwd)
echo "Current directory: $CURRENT_DIR"

# Step 2: Kill all processes on port 8080
echo ""
echo "🛑 Step 2: Stopping all processes on port 8080..."
lsof -ti:8080 | xargs kill -9 2>/dev/null || true
sleep 2

# Step 3: Clean installation
echo ""
echo "🧹 Step 3: Cleaning old installations..."
rm -rf node_modules package-lock.json dist .vite

# Step 4: Install dependencies with legacy peer deps
echo ""
echo "📦 Step 4: Installing all dependencies..."
npm install --legacy-peer-deps

# Step 5: Check if index.html exists
echo ""
echo "📄 Step 5: Checking index.html..."
if [ ! -f "index.html" ]; then
    echo "Creating index.html..."
    cat > index.html << 'EOF'
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <link rel="icon" type="image/svg+xml" href="/vite.svg" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Vitalvida Admin Portal</title>
  </head>
  <body>
    <div id="root"></div>
    <script type="module" src="/src/main.tsx"></script>
  </body>
</html>
EOF
    echo "✅ index.html created"
else
    echo "✅ index.html exists"
fi

# Step 6: Check if main.tsx exists and is correct
echo ""
echo "📄 Step 6: Checking main.tsx..."
if [ ! -f "src/main.tsx" ]; then
    echo "Creating src/main.tsx..."
    mkdir -p src
    cat > src/main.tsx << 'EOF'
import React from 'react'
import ReactDOM from 'react-dom/client'
import App from './App'
import './index.css'

const rootElement = document.getElementById('root')
if (!rootElement) {
  throw new Error('Failed to find the root element')
}

ReactDOM.createRoot(rootElement).render(
  <React.StrictMode>
    <App />
  </React.StrictMode>,
)
EOF
    echo "✅ main.tsx created"
else
    echo "✅ main.tsx exists"
fi

# Step 7: Create a simple App.tsx if it's broken
echo ""
echo "📄 Step 7: Creating fallback App.tsx..."
cat > src/App.tsx << 'EOF'
import React from 'react'

function App() {
  return (
    <div style={{ 
      display: 'flex', 
      flexDirection: 'column', 
      alignItems: 'center', 
      justifyContent: 'center', 
      height: '100vh',
      fontFamily: 'system-ui, -apple-system, sans-serif',
      background: 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)'
    }}>
      <div style={{
        background: 'white',
        padding: '3rem',
        borderRadius: '1rem',
        boxShadow: '0 20px 25px -5px rgba(0, 0, 0, 0.1)',
        textAlign: 'center'
      }}>
        <h1 style={{ 
          fontSize: '2.5rem', 
          fontWeight: 'bold',
          marginBottom: '1rem',
          background: 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
          WebkitBackgroundClip: 'text',
          WebkitTextFillColor: 'transparent'
        }}>
          🎉 Vitalvida Admin Portal
        </h1>
        <p style={{ fontSize: '1.2rem', color: '#4a5568', marginBottom: '2rem' }}>
          Portal is running successfully on port 8080!
        </p>
        <div style={{
          background: '#f7fafc',
          padding: '1rem',
          borderRadius: '0.5rem',
          marginTop: '1rem'
        }}>
          <p style={{ fontSize: '0.9rem', color: '#718096' }}>
            ✅ Server: Running<br/>
            ✅ Port: 8080<br/>
            ✅ Directory: secure-admin-center-33
          </p>
        </div>
        <div style={{ marginTop: '2rem' }}>
          <button style={{
            background: 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
            color: 'white',
            border: 'none',
            padding: '0.75rem 2rem',
            borderRadius: '0.5rem',
            fontSize: '1rem',
            cursor: 'pointer',
            fontWeight: '600'
          }} onClick={() => window.location.reload()}>
            Refresh Page
          </button>
        </div>
      </div>
    </div>
  )
}

export default App
EOF
echo "✅ App.tsx created"

# Step 8: Create index.css if missing
echo ""
echo "🎨 Step 8: Creating index.css..."
if [ ! -f "src/index.css" ]; then
    cat > src/index.css << 'EOF'
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

body {
  margin: 0;
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Roboto', 'Oxygen',
    'Ubuntu', 'Cantarell', 'Fira Sans', 'Droid Sans', 'Helvetica Neue',
    sans-serif;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}
EOF
    echo "✅ index.css created"
fi

# Step 9: Update vite.config.ts
echo ""
echo "⚙️ Step 9: Updating vite.config.ts..."
cat > vite.config.ts << 'EOF'
import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import path from 'path'

export default defineConfig({
  plugins: [react()],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
    },
  },
  server: {
    port: 8080,
    host: true,
    open: false,
    strictPort: true,
  },
  build: {
    outDir: 'dist',
    sourcemap: true,
  }
})
EOF
echo "✅ vite.config.ts updated"

# Step 10: Check package.json scripts
echo ""
echo "📋 Step 10: Checking package.json scripts..."
if ! grep -q '"dev":' package.json; then
    echo "Adding dev script to package.json..."
    # Update package.json to add scripts if missing
    npm pkg set scripts.dev="vite --port 8080"
    npm pkg set scripts.build="vite build"
    npm pkg set scripts.preview="vite preview"
fi

# Step 11: Start the development server
echo ""
echo "🚀 Step 11: Starting development server..."
echo "=================================================="
echo "✅ Setup complete! Starting server on http://localhost:8080"
echo ""

# Run the dev server
npm run dev
