#!/bin/bash
echo "🚀 Starting secure-admin-center-33 on port 8080"
lsof -ti:8080 | xargs kill -9 2>/dev/null
npm run dev
