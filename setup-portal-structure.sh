#!/bin/bash

# Create portal directories inside admin center
echo "📁 Creating portal directory structure..."
cd /Users/yesideasekun/vitalvida-api/secure-admin-center-33/

# Create portals directory if it doesn't exist
mkdir -p portals
cd portals

# Create category directories
mkdir -p role-portals
mkdir -p automation-portals

echo "✅ Directory structure created"
echo "📁 Role portals: ./portals/role-portals/"
echo "🤖 Automation portals: ./portals/automation-portals/"
