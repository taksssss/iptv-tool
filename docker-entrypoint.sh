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

# Create nginx configuration
cp /nginx.conf /etc/nginx/nginx.conf

# Update server configuration with environment variables
sed -i "s/server_name localhost;/server_name ${HTTP_SERVER_NAME};/" /etc/nginx/nginx.conf
sed -i "s/listen 80;/listen ${HTTP_PORT};/" /etc/nginx/nginx.conf
sed -i "s/error_log \/dev\/stderr info;/error_log \/dev\/stderr ${LOG_LEVEL};/" /etc/nginx/nginx.conf

# Configure PHP-FPM
sed -i "s/memory_limit = .*/memory_limit = ${PHP_MEMORY_LIMIT}/" /usr/local/etc/php/php.ini
sed -i "s#^;date.timezone =\$#date.timezone = \"${TZ}\"#" /usr/local/etc/php/php.ini
sed -i "s/upload_max_filesize = .*/upload_max_filesize = 100M/" /usr/local/etc/php/php.ini
sed -i "s/post_max_size = .*/post_max_size = 100M/" /usr/local/etc/php/php.ini

# Create php.ini from production template if it doesn't exist
if [ ! -f /usr/local/etc/php/php.ini ]; then
    cp /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini
    sed -i "s/memory_limit = .*/memory_limit = ${PHP_MEMORY_LIMIT}/" /usr/local/etc/php/php.ini
    sed -i "s#^;date.timezone =\$#date.timezone = \"${TZ}\"#" /usr/local/etc/php/php.ini
    sed -i "s/upload_max_filesize = .*/upload_max_filesize = 100M/" /usr/local/etc/php/php.ini
    sed -i "s/post_max_size = .*/post_max_size = 100M/" /usr/local/etc/php/php.ini
fi

# Configure PHP-FPM pool
sed -i 's/^user = .*/user = nginx/' /usr/local/etc/php-fpm.d/www.conf
sed -i 's/^group = .*/group = nginx/' /usr/local/etc/php-fpm.d/www.conf
sed -i 's/^listen = .*/listen = 127.0.0.1:9000/' /usr/local/etc/php-fpm.d/www.conf

# Modify system timezone
if [ -e /etc/localtime ]; then rm -f /etc/localtime; fi
ln -s /usr/share/zoneinfo/${TZ} /etc/localtime

echo 'Running cron.php and Nginx'

# Change ownership of /htdocs
chown -R nginx:nginx /htdocs

# Start cron.php
cd /htdocs
su -s /bin/sh -c "php cron.php &" "nginx"

# Start PHP-FPM and Nginx
php-fpm -D
nginx -g "daemon off;"