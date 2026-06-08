# 🚀 Deployment Guide — OSINT Platform v2.0

## Pre-Deployment Checklist

- [ ] All tests pass (see TESTING.md)
- [ ] No API keys or credentials in code
- [ ] Database backups created
- [ ] HTTPS certificate configured
- [ ] Error logging configured
- [ ] Rate limiting configured
- [ ] Firewall rules updated
- [ ] User documentation reviewed
- [ ] Team trained on system

---

## Deployment Environments

### Development (Your Machine)
```
http://localhost/fake-news-platform-b/
```

### Staging (Test Server)
```
https://staging.osint.company.local/
```

### Production (Live Server)
```
https://osint.company.com/
```

---

## Server Requirements

### Hardware
- **CPU**: 2+ cores (Python async + PHP)
- **RAM**: 4GB minimum (Chrome requires ~300MB per instance)
- **Storage**: 50GB+ for database + images
- **Network**: 10 Mbps+ for reliable Chrome automation

### Software
- **OS**: Windows Server 2019+, Ubuntu 20.04+, CentOS 8+
- **PHP**: 8.0+ with PDO MySQL
- **MySQL**: 5.7+ or MariaDB 10.3+
- **Python**: 3.9+ with pip
- **Chrome**: Latest stable (for facebook_post_extractor.py)
- **Node.js**: Optional (for build tools, not required)

---

## Installation (Production)

### Step 1: Clone/Upload Code

**Option A: Git Clone**
```bash
cd /var/www/
git clone https://github.com/yourorg/fake-news-platform.git
cd fake-news-platform-b
```

**Option B: SFTP Upload**
```bash
# Using FileZilla or WinSCP:
# Upload all files to: /var/www/fake-news-platform-b/
# Ensure executable permissions on python files
```

### Step 2: Install Python Dependencies

```bash
# Navigate to project
cd /var/www/fake-news-platform-b

# Create virtual environment
python -m venv venv

# Activate (Linux/Mac)
source venv/bin/activate

# Activate (Windows)
.\venv\Scripts\Activate

# Install requirements
pip install -r python-ai/requirements.txt

# Install Playwright browsers
playwright install
```

### Step 3: Configure Environment

**Create `.env` file:**
```bash
# /var/www/fake-news-platform-b/.env
DB_HOST=localhost
DB_USER=osint_user
DB_PASS=SecurePassword123!
DB_NAME=fake_news_platform

PHP_LOG_FILE=/var/log/osint/php_error.log
PYTHON_LOG_FILE=/var/log/osint/python.log
EXTRACTION_LOG_FILE=/var/log/osint/extraction.log

# Chrome configuration
CHROME_EXECUTABLE=/usr/bin/google-chrome
CHROME_USER_DATA=/var/osint/chrome_session
CHROME_HEADLESS=true

# Security
ENABLE_RATE_LIMIT=true
RATE_LIMIT_REQUESTS=10
RATE_LIMIT_WINDOW=60

# AI Model
AI_MODEL=cardiffnlp/twitter-xlm-roberta-base-sentiment
```

**Update `includes/config.php`:**
```php
<?php
// Load .env
$env = parse_ini_file(__DIR__ . '/../.env');

define('DB_HOST', $env['DB_HOST']);
define('DB_USER', $env['DB_USER']);
define('DB_PASS', $env['DB_PASS']);
define('DB_NAME', $env['DB_NAME']);

define('LOG_FILE', $env['PHP_LOG_FILE']);
define('DEBUG_MODE', false);  // Set to true only in development
?>
```

### Step 4: Create MySQL Database

```bash
# Connect to MySQL
mysql -u root -p

# Create database and user
CREATE DATABASE fake_news_platform CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER 'osint_user'@'localhost' IDENTIFIED BY 'SecurePassword123!';
GRANT ALL PRIVILEGES ON fake_news_platform.* TO 'osint_user'@'localhost';

FLUSH PRIVILEGES;
EXIT;
```

### Step 5: Initialize Database Schema

```bash
# Navigate to project
cd /var/www/fake-news-platform-b

# Execute initialization
php database/init_facebook_posts.php

# Or via MySQL directly
mysql -u osint_user -p fake_news_platform < database/schema.sql
```

### Step 6: Configure Web Server

**Apache (Linux):**
```apache
# /etc/apache2/sites-available/osint.conf
<VirtualHost *:443>
    ServerName osint.company.com
    ServerAlias www.osint.company.com
    
    DocumentRoot /var/www/fake-news-platform-b
    
    <Directory /var/www/fake-news-platform-b>
        Options FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    # SSL Configuration
    SSLEngine on
    SSLCertificateFile /etc/ssl/certs/cert.pem
    SSLCertificateKeyFile /etc/ssl/private/key.pem
    
    # Security Headers
    Header set X-Content-Type-Options "nosniff"
    Header set X-Frame-Options "SAMEORIGIN"
    Header set X-XSS-Protection "1; mode=block"
    Header set Referrer-Policy "strict-origin-when-cross-origin"
    
    # Enable PHP
    <FilesMatch "\\.php$">
        SetHandler "proxy:unix:/run/php/php8.1-fpm.sock|fcgi://localhost"
    </FilesMatch>
    
    # Deny access to sensitive files
    <FilesMatch "(config\\.php|schema\\.sql|\\.env|composer\\.lock)">
        Require all denied
    </FilesMatch>
    
    # Logging
    ErrorLog /var/log/apache2/osint_error.log
    CustomLog /var/log/apache2/osint_access.log combined
</VirtualHost>

# Enable site
a2ensite osint
a2enmod ssl
a2enmod headers
systemctl reload apache2
```

**Nginx (Linux):**
```nginx
# /etc/nginx/sites-available/osint
upstream php-fpm {
    server unix:/run/php/php8.1-fpm.sock;
}

server {
    listen 443 ssl http2;
    server_name osint.company.com www.osint.company.com;
    
    root /var/www/fake-news-platform-b;
    index index.php;
    
    # SSL
    ssl_certificate /etc/ssl/certs/cert.pem;
    ssl_certificate_key /etc/ssl/private/key.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;
    
    # Security Headers
    add_header X-Content-Type-Options "nosniff";
    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-XSS-Protection "1; mode=block";
    
    # Deny access to sensitive files
    location ~ /(\\.env|config\\.php|schema\\.sql|composer\\.lock) {
        deny all;
    }
    
    # PHP routing
    location ~ \\.php$ {
        fastcgi_pass php-fpm;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
    
    # Static files caching
    location ~* \\.(jpg|jpeg|png|gif|css|js|ico)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
    
    # Logging
    access_log /var/log/nginx/osint_access.log;
    error_log /var/log/nginx/osint_error.log;
    
    # Redirect HTTP to HTTPS
    listen 80;
    server_name osint.company.com www.osint.company.com;
    return 301 https://$server_name$request_uri;
}
```

### Step 7: Set File Permissions

```bash
# Navigate to project
cd /var/www/fake-news-platform-b

# Web server user (typically www-data on Linux, _www on macOS)
sudo chown -R www-data:www-data .

# Directories need write access
chmod 755 .
chmod 755 api
chmod 755 pages
chmod 755 python-ai
chmod 755 uploads
chmod 755 database

# Files
find . -type f -name "*.php" -exec chmod 644 {} \;
find . -type f -name "*.py" -exec chmod 755 {} \;

# Logs directory
mkdir -p /var/log/osint
sudo chown www-data:www-data /var/log/osint
chmod 755 /var/log/osint

# Python venv scripts executable
chmod +x venv/bin/python
chmod +x venv/bin/python3
```

### Step 8: Configure Supervisor (for background tasks)

**Python workers (optional, for async processing):**

```ini
# /etc/supervisor/conf.d/osint_workers.conf
[program:osint_worker]
process_name=%(program_name)s_%(process_num)02d
command=/var/www/fake-news-platform-b/venv/bin/python python-ai/worker.py
directory=/var/www/fake-news-platform-b
autostart=true
autorestart=true
numprocs=2
priority=999
user=www-data
stdout_logfile=/var/log/osint/worker_%(process_num)02d.log
stderr_logfile=/var/log/osint/worker_%(process_num)02d.log
```

**Reload supervisor:**
```bash
supervisorctl reread
supervisorctl update
supervisorctl start osint_worker:*
```

---

## Security Hardening

### 1. Firewall Rules

```bash
# Only allow HTTPS
sudo ufw allow 443/tcp
sudo ufw deny 80/tcp     # Optional: deny HTTP

# Restrict API access to known IPs (if applicable)
sudo ufw allow from 203.0.113.0/24 to any port 443
```

### 2. Database Security

```sql
-- Create restricted user (not root)
CREATE USER 'osint_api'@'localhost' IDENTIFIED BY 'SecurePass123!';
GRANT SELECT, INSERT, UPDATE, DELETE ON fake_news_platform.* TO 'osint_api'@'localhost';

-- Disable FILE privilege
REVOKE FILE ON *.* FROM 'osint_api'@'localhost';

-- Disable SUPER privilege
REVOKE SUPER ON *.* FROM 'osint_api'@'localhost';
```

### 3. PHP Security

**`php.ini` settings:**
```ini
# Disable dangerous functions
disable_functions = exec, passthru, shell_exec, system, proc_open, popen, curl_exec, curl_multi_exec, parse_ini_file, show_source

# Disable file uploads to web root
upload_tmp_dir = /tmp
upload_max_filesize = 10M
post_max_size = 10M

# Session security
session.use_strict_mode = 1
session.cookie_secure = 1        # HTTPS only
session.cookie_httponly = 1      # No JavaScript access
session.cookie_samesite = Strict

# Error reporting
error_reporting = E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED
display_errors = Off
log_errors = On
error_log = /var/log/osint/php_error.log
```

### 4. Hide Version Information

**Apache:**
```apache
ServerTokens Prod
ServerSignature Off
```

**Nginx:**
```nginx
server_tokens off;
```

### 5. Rate Limiting

**Add to `includes/config.php`:**
```php
<?php
// Rate limit function
function checkRateLimit($ip, $limit = 10, $window = 60) {
    $key = "rate_limit_" . md5($ip);
    $cache_file = "/tmp/" . $key;
    
    if (file_exists($cache_file)) {
        $data = json_decode(file_get_contents($cache_file), true);
        if (time() - $data['time'] < $window) {
            if ($data['count'] >= $limit) {
                http_response_code(429); // Too Many Requests
                die(json_encode(["error" => "Rate limit exceeded"]));
            }
            $data['count']++;
        } else {
            $data = ["count" => 1, "time" => time()];
        }
    } else {
        $data = ["count" => 1, "time" => time()];
    }
    
    file_put_contents($cache_file, json_encode($data));
}

// In API files
$ip = $_SERVER['REMOTE_ADDR'];
checkRateLimit($ip, 10, 60);  // 10 requests per minute
?>
```

---

## Monitoring & Maintenance

### 1. Log Rotation

**`/etc/logrotate.d/osint`:**
```
/var/log/osint/*.log {
    daily
    rotate 30
    compress
    delaycompress
    missingok
    notifempty
    create 0644 www-data www-data
    sharedscripts
}
```

### 2. Database Backups

**Daily backup script:**
```bash
#!/bin/bash
# /usr/local/bin/backup_osint.sh

BACKUP_DIR="/backups/osint"
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_FILE="$BACKUP_DIR/fake_news_platform_$DATE.sql"

mkdir -p $BACKUP_DIR

mysqldump -u osint_user -p --single-transaction --quick \
  fake_news_platform > $BACKUP_FILE

gzip $BACKUP_FILE

# Keep only last 30 days
find $BACKUP_DIR -name "*.sql.gz" -mtime +30 -delete

echo "Backup completed: $BACKUP_FILE.gz"
```

**Crontab entry:**
```bash
0 2 * * * /usr/local/bin/backup_osint.sh >> /var/log/osint/backup.log 2>&1
```

### 3. Health Checks

**Monitoring script:**
```bash
#!/bin/bash
# /usr/local/bin/check_osint_health.sh

# Check if web server is running
curl -sf https://osint.company.com/pages/publications.php > /dev/null || \
  echo "ERROR: Web server down" | mail -s "OSINT Alert" admin@company.com

# Check if MySQL is running
mysql -u osint_user -p -e "SELECT 1;" > /dev/null || \
  echo "ERROR: Database down" | mail -s "OSINT Alert" admin@company.com

# Check disk space
USAGE=$(df /var/www | awk 'NR==2 {print $5}' | cut -d% -f1)
if [ $USAGE -gt 80 ]; then
  echo "WARNING: Disk usage at ${USAGE}%" | mail -s "OSINT Alert" admin@company.com
fi

# Check if Chrome is available
which google-chrome > /dev/null || \
  echo "ERROR: Chrome not found" | mail -s "OSINT Alert" admin@company.com
```

**Crontab entry (every 15 minutes):**
```bash
*/15 * * * * /usr/local/bin/check_osint_health.sh
```

### 4. Performance Monitoring

**PHP Performance Module:**
```bash
# Install SPM (optional, for production monitoring)
pip install spm

# Or use DataDog agent
curl -sSL https://s3.amazonaws.com/dd-agent/scripts/install_agent.sh | bash
```

---

## Rollback Procedures

### If Something Goes Wrong

```bash
# 1. Restore database from backup
mysql -u osint_user -p fake_news_platform < /backups/osint/fake_news_platform_20260526_020000.sql

# 2. Restore code from git
cd /var/www/fake-news-platform-b
git reset --hard HEAD~1    # Go back 1 commit
git pull origin main

# 3. Restart services
systemctl restart apache2  # or nginx
systemctl restart php8.1-fpm
systemctl restart supervisor

# 4. Verify system
curl -sf https://osint.company.com/pages/publications.php
```

---

## Post-Deployment Testing

```bash
# 1. Check HTTPS certificate
echo | openssl s_client -servername osint.company.com \
  -connect osint.company.com:443 2>/dev/null | grep "Verify return code"

# 2. Test API endpoint
curl -X GET https://osint.company.com/api/facebook_post_api.php?action=get_recent_posts

# 3. Check database connectivity
php -r "require('includes/config.php'); echo 'DB: OK';"

# 4. Verify Python access
/var/www/fake-news-platform-b/venv/bin/python --version

# 5. Check Chrome installation
which google-chrome && echo "Chrome: OK"
```

---

## Scaling Considerations

### Horizontal Scaling (Multiple Servers)

1. **Load Balancer** (HAProxy/Nginx)
   - Distribute traffic across multiple PHP servers
   - Session sharing via Redis/Memcached

2. **Database Replication**
   - Master-Slave MySQL setup
   - Read replicas for reporting

3. **Python Worker Queue**
   - Celery + Redis for async extraction/analysis
   - Multiple worker processes

### Vertical Scaling (Bigger Server)

1. Increase MySQL buffer pool size
2. Increase PHP-FPM pool size
3. Allocate more RAM for Chrome instances

---

## Support & Troubleshooting

**Common Production Issues:**

| Issue | Solution |
|-------|----------|
| High CPU | Check if Chrome processes running wild; restart Python workers |
| High Memory | Increase server RAM; reduce concurrent extractions |
| Slow DB | Enable query caching; add indexes; archiveold posts |
| SSL Errors | Renew certificate; update cron job for auto-renewal |
| Log file size | Configure log rotation (already provided above) |

---

**Version**: 2.0
**Last Updated**: 26 May 2026
**Status**: Ready for Production
