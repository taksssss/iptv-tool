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

# Build token regex from config for proxy gate
TOKEN_RANGE=1
TOKEN_PATTERN=""
if [ -f /htdocs/data/config.json ]; then
    TOKEN_RANGE=$(jq -r '.token_range // 1' /htdocs/data/config.json 2>/dev/null || echo 1)
    TOKENS_RAW=$(jq -r '.token // ""' /htdocs/data/config.json 2>/dev/null | tr -d '\r')
    if [ -n "$TOKENS_RAW" ]; then
        while IFS= read -r line; do
            t=$(echo "$line" | xargs)
            [ -z "$t" ] && continue
            if printf '%s' "$t" | grep -q '^regex:'; then
                pat=${t#regex:}
                entry="(${pat})"
            else
                # Escape regex metachars
                esc=$(printf '%s' "$t" | sed -e 's/[\\.^$*+?()[\]{}|]/\\&/g')
                entry="$esc"
            fi
            if [ -z "$TOKEN_PATTERN" ]; then
                TOKEN_PATTERN="$entry"
            else
                TOKEN_PATTERN="$TOKEN_PATTERN|$entry"
            fi
        done <<EOL
$TOKENS_RAW
EOL
    fi
fi

# Compose token env gating rules (proxy.php only used on failure)
TOKEN_GATE_RULES="    # No token enforcement (allow all)\n    RewriteRule ^/proxy\\.php$ - [E=ALLOW_PROXY:1]"
if [ "${TOKEN_RANGE}" != "0" ] && [ -n "${TOKEN_PATTERN}" ]; then
    TOKEN_GATE_RULES="    # Token validation for proxy\n    RewriteCond %{QUERY_STRING} (^|&)token=([^&]+) [NC]\n    RewriteCond %2 ^(${TOKEN_PATTERN})$ [NC]\n    RewriteRule ^/proxy\\.php$ - [E=ALLOW_PROXY:1]\n\n    # If not allowed, route to PHP auth handler\n    RewriteCond %{ENV:ALLOW_PROXY} !=1\n    RewriteRule ^/proxy\\.php$ /proxy.php?auth=fail [L]"
fi

# Write URL rewrite rules to conf.d/rewrite.conf (with proxy forwarding)
cat > /etc/apache2/conf.d/rewrite.conf <<EOF
<IfModule mod_rewrite.c>
    RewriteEngine On

    # Harden proxy behavior
    ProxyRequests Off

    # proxy.php forwarding via mod_proxy with basic SSRF guards
${TOKEN_GATE_RULES}

    # 1) Extract full URL and host from query string when allowed
    RewriteCond %{ENV:ALLOW_PROXY} =1
    RewriteCond %{QUERY_STRING} (^|&)url=(https?://([^/:&]+)[^&]*) [NC]
    # 2) Block localhost and obvious private IPv4/IPv6 literals
    RewriteCond %3 !^(localhost|127\.|10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[0-1])\.|\[::1\]) [NC]
    # 3) Proxy to captured URL
    RewriteRule ^/proxy\.php$ %2 [P,L,NE]

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
sed -i "s/#ServerName\ www.example.com:80/ServerName\ ${HTTP_SERVER_NAME}/" /etc/apache2/httpd.conf
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
sed -i "s/ServerName\ www.example.com:443/ServerName\ ${HTTPS_SERVER_NAME}/" /etc/apache2/conf.d/ssl.conf

# Re-define LogLevel
sed -i "s#^LogLevel .*#LogLevel ${LOG_LEVEL}#g" /etc/apache2/httpd.conf

# Enable commonly used apache modules
sed -i 's/#LoadModule\ rewrite_module/LoadModule\ rewrite_module/' /etc/apache2/httpd.conf
sed -i 's/#LoadModule\ deflate_module/LoadModule\ deflate_module/' /etc/apache2/httpd.conf
sed -i 's/#LoadModule\ expires_module/LoadModule\ expires_module/' /etc/apache2/httpd.conf
sed -i 's/#LoadModule\ proxy_module/LoadModule\ proxy_module/' /etc/apache2/httpd.conf
sed -i 's/#LoadModule\ proxy_http_module/LoadModule\ proxy_http_module/' /etc/apache2/httpd.conf
sed -i 's/#LoadModule\ proxy_connect_module/LoadModule\ proxy_connect_module/' /etc/apache2/httpd.conf

# Modify php memory limit, timezone and file size limit
sed -i "s/memory_limit = .*/memory_limit = ${PHP_MEMORY_LIMIT}/" /etc/php83/php.ini
sed -i "s#^;date.timezone =$#date.timezone = \"${TZ}\"#" /etc/php83/php.ini
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