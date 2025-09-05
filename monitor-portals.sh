#!/bin/bash

# Portal Monitor Script - Keeps all portals running
# Run this script in the background to monitor portals

echo "🚀 Portal Monitor Started - Checking every 30 seconds..."

while true; do
    echo "🔍 Checking portals at $(date)..."
    
    # Check each portal and restart if dead
    if ! lsof -i :3004 > /dev/null 2>&1; then
        echo "⚠️  VitalVida Inventory (3004) down - restarting..."
        cd vitalvida-inventory && npm run dev > /dev/null 2>&1 &
    fi
    
    if ! lsof -i :3005 > /dev/null 2>&1; then
        echo "⚠️  Financial Controller (3005) down - restarting..."
        cd financial-controller && npm run dev > /dev/null 2>&1 &
    fi
    
    if ! lsof -i :3007 > /dev/null 2>&1; then
        echo "⚠️  Delivery Agent (3007) down - restarting..."
        cd delivery-agent && npm run dev > /dev/null 2>&1 &
    fi
    
    if ! lsof -i :3009 > /dev/null 2>&1; then
        echo "⚠️  Inventory Management (3009) down - restarting..."
        cd inventory-management && npm run dev > /dev/null 2>&1 &
    fi
    
    if ! lsof -i :3012 > /dev/null 2>&1; then
        echo "⚠️  HR (3012) down - restarting..."
        cd hr && npm run dev > /dev/null 2>&1 &
    fi
    
    if ! lsof -i :3013 > /dev/null 2>&1; then
        echo "⚠️  Logistics (3013) down - restarting..."
        cd logistics && npm run dev > /dev/null 2>&1 &
    fi
    
    if ! lsof -i :3014 > /dev/null 2>&1; then
        echo "⚠️  Manufacturing (3014) down - restarting..."
        cd manufacturing && npm run dev > /dev/null 2>&1 &
    fi
    
    if ! lsof -i :3015 > /dev/null 2>&1; then
        echo "⚠️  Media Buyer (3015) down - restarting..."
        cd media-buyer && npm run dev > /dev/null 2>&1 &
    fi
    
    if ! lsof -i :3016 > /dev/null 2>&1; then
        echo "⚠️  Telesales (3016) down - restarting..."
        cd telesales && npm run dev > /dev/null 2>&1 &
    fi
    
    if ! lsof -i :3019 > /dev/null 2>&1; then
        echo "⚠️  VitalVida Marketing (3019) down - restarting..."
        cd vitalvida-marketing && npm run dev > /dev/null 2>&1 &
    fi
    
    if ! lsof -i :8080 > /dev/null 2>&1; then
        echo "⚠️  Admin Center (8080) down - restarting..."
        cd secure-admin-center-33 && npm run dev > /dev/null 2>&1 &
    fi
    
    echo "✅ All portals checked. Sleeping for 30 seconds..."
    sleep 30
done
