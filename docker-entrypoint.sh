#!/bin/sh

# Exit on non defined variables and on non zero exit codes
set -eu

SERVER_ADMIN="${SERVER_ADMIN:-you@example.com}"
HTTP_SERVER_NAME="${HTTP_SERVER_NAME:-www.example.com}"
HTTPS_SERVER_NAME="${HTTPS_SERVER_NAME:-www.example.com}"
LOG_LEVEL="${LOG_LEVEL:-info}"
TZ="${TZ:-Asia/Shanghai}"
PHP_MEMORY_LIMIT="${PHP_MEMORY_LIMIT:-512M}"
ENABLE_FFMPEG="${ENABLE_FFMPEG:-false}"
HTTP_PORT="${HTTP_PORT:-80}"
HTTPS_PORT="${HTTPS_PORT:-443}"

echo 'Updating configurations'

# Check and install ffmpeg if ENABLE_FFMPEG is set to true
if [ "$ENABLE_FFMPEG" = "true" ]; then
    echo "Using USTC mirror for package installation..."
    sed -i 's/dl-cdn.alpinelinux.org/mirrors.ustc.edu.cn/g' /etc/apk/repositories
    if ! apk info ffmpeg > /dev/null 2>&1; then
        echo "Installing ffmpeg..."
        apk add --no-cache ffmpeg
    else
        echo "ffmpeg is already installed."
    fi
else
    echo "Skipping ffmpeg installation."
fi

# Check if the required configuration is already present
if ! grep -q "# Directory Listing Disabled" /etc/apache2/httpd.conf; then
cat <<EOF >> /etc/apache2/httpd.conf
# Directory Listing Disabled
<Directory "/htdocs">
    Options -Indexes
    AllowOverride All
    Require all granted
</Directory>

# Block access to /htdocs/data except for /htdocs/data/icon
<Directory "/htdocs/data">
    Require all denied
</Directory>

<Location "/data/icon">
    Require all granted
</Location>
EOF
fi

# Write URL rewrite rules to conf.d/rewrite.conf
cat <<'EOF' > /etc/apache2/conf.d/rewrite.conf
<IfModule mod_rewrite.c>
    RewriteEngine On

    # /tv.m3u
    RewriteCond %{QUERY_STRING} !(^|&)type=m3u(&|$)
    RewriteRule ^/tv\.m3u$ /index.php?type=m3u&%{QUERY_STRING} [L]

    # /tv.txt
    RewriteCond %{QUERY_STRING} !(^|&)type=txt(&|$)
    RewriteRule ^/tv\.txt$ /index.php?type=txt&%{QUERY_STRING} [L]

    # /t.xml
    RewriteCond %{QUERY_STRING} !(^|&)type=xml(&|$)
    RewriteRule ^/t\.xml$ /index.php?type=xml&%{QUERY_STRING} [L]

    # /t.xml.gz
    RewriteCond %{QUERY_STRING} !(^|&)type=gz(&|$)
    RewriteRule ^/t\.xml\.gz$ /index.php?type=gz&%{QUERY_STRING} [L]
</IfModule>
EOF

# Change Server Admin, Name, Document Root
sed -i "s/ServerAdmin\ you@example.com/ServerAdmin\ ${SERVER_ADMIN}/" /etc/apache2/httpd.conf
sed -i "s/#ServerName\ www.example.com:80/ServerName\ ${HTTP_SERVER_NAME}:${HTTP_PORT}/" /etc/apache2/httpd.conf
sed -i 's#^DocumentRoot ".*#DocumentRoot "/htdocs"#g' /etc/apache2/httpd.conf
sed -i 's#Directory "/var/www/localhost/htdocs"#Directory "/htdocs"#g' /etc/apache2/httpd.conf
sed -i 's#AllowOverride None#AllowOverride All#' /etc/apache2/httpd.conf

# Change TransferLog after ErrorLog
sed -i 's#^ErrorLog .*#ErrorLog "/dev/stderr"\nTransferLog "/dev/null"#g' /etc/apache2/httpd.conf
sed -i 's#CustomLog .* combined#CustomLog "/dev/null" combined#g' /etc/apache2/httpd.conf

# SSL DocumentRoot and Log locations
sed -i 's#^ErrorLog .*#ErrorLog "/dev/stderr"#g' /etc/apache2/conf.d/ssl.conf
sed -i 's#^TransferLog .*#TransferLog "/dev/null"#g' /etc/apache2/conf.d/ssl.conf
sed -i 's#^DocumentRoot ".*#DocumentRoot "/htdocs"#g' /etc/apache2/conf.d/ssl.conf
sed -i "s/ServerAdmin\ you@example.com/ServerAdmin\ ${SERVER_ADMIN}/" /etc/apache2/conf.d/ssl.conf
sed -i "s/ServerName\ www.example.com:443/ServerName\ ${HTTPS_SERVER_NAME}:${HTTPS_PORT}/" /etc/apache2/conf.d/ssl.conf

# Re-define LogLevel
sed -i "s#^LogLevel .*#LogLevel ${LOG_LEVEL}#g" /etc/apache2/httpd.conf

# Enable commonly used apache modules
sed -i 's/#LoadModule\ rewrite_module/LoadModule\ rewrite_module/' /etc/apache2/httpd.conf
sed -i 's/#LoadModule\ deflate_module/LoadModule\ deflate_module/' /etc/apache2/httpd.conf
sed -i 's/#LoadModule\ expires_module/LoadModule\ expires_module/' /etc/apache2/httpd.conf

# Configure dynamic ports
sed -i "s/^Listen 80$/Listen ${HTTP_PORT}/" /etc/apache2/httpd.conf
sed -i "s/^Listen 443$/Listen ${HTTPS_PORT}/" /etc/apache2/conf.d/ssl.conf

# Modify php memory limit, timezone and file size limit
sed -i "s/memory_limit = .*/memory_limit = ${PHP_MEMORY_LIMIT}/" /etc/php83/php.ini
sed -i "s#^;date.timezone =\$#date.timezone = \"${TZ}\"#" /etc/php83/php.ini
sed -i "s/upload_max_filesize = .*/upload_max_filesize = 100M/" /etc/php83/php.ini
sed -i "s/post_max_size = .*/post_max_size = 100M/" /etc/php83/php.ini

# Modify system timezone
if [ -e /etc/localtime ]; then rm -f /etc/localtime; fi
ln -s /usr/share/zoneinfo/${TZ} /etc/localtime

echo 'Running cron.php and Apache'

# Change ownership of /htdocs
chown -R apache:apache /htdocs

# Start cron.php
cd /htdocs
su -s /bin/sh -c "php cron.php &" "apache"

# Remove stale PID file
if [ -f /run/apache2/httpd.pid ]; then
    echo "Removing stale httpd PID file"
    rm -f /run/apache2/httpd.pid
fi

# Start Memcached and Apache
memcached -u nobody -d && httpd -D FOREGROUND