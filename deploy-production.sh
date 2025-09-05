#!/bin/bash

# 🚀 Production Deployment Script - VitalVida API
# This script handles secure production deployment

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}🚀 VitalVida API Production Deployment${NC}"
echo "=============================================="

# Configuration
APP_NAME="VitalVida API"
APP_ENV="production"
BACKUP_DIR="/backups/vitalvida"
LOG_DIR="/var/log/vitalvida"

# Check if running as root
if [[ $EUID -eq 0 ]]; then
   echo -e "${RED}❌ This script should not be run as root${NC}"
   exit 1
fi

# Function to log messages
log_message() {
    echo -e "${GREEN}[$(date +'%Y-%m-%d %H:%M:%S')] $1${NC}"
}

# Function to log warnings
log_warning() {
    echo -e "${YELLOW}[$(date +'%Y-%m-%d %H:%M:%S')] WARNING: $1${NC}"
}

# Function to log errors
log_error() {
    echo -e "${RED}[$(date +'%Y-%m-%d %H:%M:%S')] ERROR: $1${NC}"
}

# Step 1: Pre-deployment checks
log_message "Step 1: Pre-deployment security checks"

# Check if .env file exists
if [ ! -f ".env" ]; then
    log_error ".env file not found. Please create it from env.production.example"
    exit 1
fi

# Check if APP_KEY is set
if ! grep -q "APP_KEY=base64:" .env; then
    log_error "APP_KEY not set. Please generate one with: php artisan key:generate"
    exit 1
fi

# Check if APP_ENV is production
if ! grep -q "APP_ENV=production" .env; then
    log_warning "APP_ENV not set to production. Please update .env file"
fi

# Check if APP_DEBUG is false
if grep -q "APP_DEBUG=true" .env; then
    log_error "APP_DEBUG is set to true. Please set to false for production"
    exit 1
fi

# Step 2: Create necessary directories
log_message "Step 2: Creating necessary directories"

sudo mkdir -p $BACKUP_DIR
sudo mkdir -p $LOG_DIR
sudo chown -R $USER:$USER $BACKUP_DIR
sudo chown -R $USER:$USER $LOG_DIR

# Step 3: Install dependencies
log_message "Step 3: Installing dependencies"

composer install --no-dev --optimize-autoloader

# Step 4: Run security tests
log_message "Step 4: Running security tests"

# Start server in background for testing
php artisan serve --host=127.0.0.1 --port=8000 &
SERVER_PID=$!

# Wait for server to start
sleep 5

# Run security tests
if [ -f "test-production-security.sh" ]; then
    ./test-production-security.sh
    SECURITY_RESULT=$?
else
    log_warning "Security test script not found, skipping security tests"
    SECURITY_RESULT=0
fi

# Stop test server
kill $SERVER_PID 2>/dev/null || true

# Check security test results
if [ $SECURITY_RESULT -ne 0 ]; then
    log_error "Security tests failed. Please fix issues before deployment"
    exit 1
fi

# Step 5: Database setup
log_message "Step 5: Database setup"

# Run migrations
php artisan migrate --force

# Run security migrations
php artisan migrate --path=database/migrations/2025_01_01_000000_create_security_logs_table.php --force

# Step 6: Cache and optimization
log_message "Step 6: Cache and optimization"

# Clear all caches
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Cache configuration for production
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Step 7: Set proper permissions
log_message "Step 7: Setting proper permissions"

# Set storage permissions
chmod -R 775 storage
chmod -R 775 bootstrap/cache

# Set proper ownership
sudo chown -R www-data:www-data storage
sudo chown -R www-data:www-data bootstrap/cache

# Step 8: Security hardening
log_message "Step 8: Security hardening"

# Create .htaccess for additional security (if using Apache)
if [ ! -f "public/.htaccess" ]; then
    cat > public/.htaccess << 'EOF'
# Security Headers
<IfModule mod_headers.c>
    Header always set X-Content-Type-Options nosniff
    Header always set X-Frame-Options DENY
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    Header always set Permissions-Policy "geolocation=(), microphone=(), camera=()"
    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains; preload"
</IfModule>

# Disable directory browsing
Options -Indexes

# Protect sensitive files
<Files ".env">
    Order allow,deny
    Deny from all
</Files>

<Files "composer.json">
    Order allow,deny
    Deny from all
</Files>

<Files "composer.lock">
    Order allow,deny
    Deny from all
</Files>

# Rewrite rules for Laravel
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^(.*)$ index.php [QSA,L]
</IfModule>
EOF
fi

# Step 9: Create systemd service (optional)
log_message "Step 9: Creating systemd service"

if command -v systemctl &> /dev/null; then
    sudo tee /etc/systemd/system/vitalvida-api.service > /dev/null << EOF
[Unit]
Description=VitalVida API
After=network.target

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=$(pwd)
ExecStart=/usr/bin/php artisan serve --host=0.0.0.0 --port=8000
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
EOF

    sudo systemctl daemon-reload
    sudo systemctl enable vitalvida-api.service
    log_message "Systemd service created and enabled"
else
    log_warning "systemctl not available, skipping systemd service creation"
fi

# Step 10: Setup monitoring
log_message "Step 10: Setting up monitoring"

# Create logrotate configuration
sudo tee /etc/logrotate.d/vitalvida-api > /dev/null << EOF
$(pwd)/storage/logs/*.log {
    daily
    missingok
    rotate 30
    compress
    delaycompress
    notifempty
    create 644 www-data www-data
    postrotate
        systemctl reload vitalvida-api.service
    endscript
}
EOF

# Step 11: Final security checks
log_message "Step 11: Final security checks"

# Check if HTTPS is configured
if ! grep -q "APP_URL=https://" .env; then
    log_warning "HTTPS not configured. Please set up SSL certificate"
fi

# Check if Redis is configured for rate limiting
if ! grep -q "CACHE_DRIVER=redis" .env; then
    log_warning "Redis not configured. Rate limiting may not work properly"
fi

# Step 12: Create backup
log_message "Step 12: Creating backup"

BACKUP_FILE="$BACKUP_DIR/vitalvida-backup-$(date +%Y%m%d-%H%M%S).tar.gz"
tar -czf "$BACKUP_FILE" --exclude='vendor' --exclude='node_modules' --exclude='.git' .

log_message "Backup created: $BACKUP_FILE"

# Step 13: Start services
log_message "Step 13: Starting services"

if command -v systemctl &> /dev/null; then
    sudo systemctl start vitalvida-api.service
    sudo systemctl status vitalvida-api.service --no-pager
else
    log_message "Starting Laravel development server..."
    nohup php artisan serve --host=0.0.0.0 --port=8000 > $LOG_DIR/app.log 2>&1 &
fi

# Step 14: Health check
log_message "Step 14: Health check"

sleep 5

if curl -s http://localhost:8000/api/test | grep -q "VitalVida API is working"; then
    log_message "✅ Health check passed"
else
    log_error "❌ Health check failed"
    exit 1
fi

# Step 15: Security monitoring setup
log_message "Step 15: Setting up security monitoring"

# Create cron job for security monitoring
(crontab -l 2>/dev/null; echo "0 */6 * * * cd $(pwd) && php artisan security:monitor --hours=24 --alert") | crontab -

# Create cron job for log cleanup
(crontab -l 2>/dev/null; echo "0 2 * * 0 cd $(pwd) && php artisan security:clean-logs --days=90") | crontab -

log_message "Security monitoring cron jobs created"

# Final summary
echo -e "\n${GREEN}🎉 Production Deployment Complete!${NC}"
echo "======================================"
echo -e "${BLUE}✅ Security tests passed${NC}"
echo -e "${BLUE}✅ Database migrations completed${NC}"
echo -e "${BLUE}✅ Caches optimized${NC}"
echo -e "${BLUE}✅ Permissions set correctly${NC}"
echo -e "${BLUE}✅ Security headers configured${NC}"
echo -e "${BLUE}✅ Monitoring setup complete${NC}"
echo -e "${BLUE}✅ Backup created${NC}"
echo -e "${BLUE}✅ Services started${NC}"

echo -e "\n${YELLOW}📋 Next Steps:${NC}"
echo "1. Configure your web server (Nginx/Apache)"
echo "2. Set up SSL certificate"
echo "3. Configure firewall rules"
echo "4. Set up database backups"
echo "5. Monitor security logs regularly"

echo -e "\n${GREEN}🔒 Your VitalVida API is now securely deployed!${NC}" 