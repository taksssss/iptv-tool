#!/bin/sh
set -eu

SERVER_NAME="${SERVER_NAME:-www.example.com}"
LOG_LEVEL="${LOG_LEVEL:-info}"
TZ="${TZ:-Asia/Shanghai}"
PHP_MEMORY_LIMIT="${PHP_MEMORY_LIMIT:-512M}"
ENABLE_FFMPEG="${ENABLE_FFMPEG:-false}"
ENABLE_IPV6="${ENABLE_IPV6:-false}"

HTTP_PORT="${HTTP_PORT:-80}"
HTTPS_PORT="${HTTPS_PORT:-443}"
FFMPEG_DOWNLOAD_RETRIES="${FFMPEG_DOWNLOAD_RETRIES:-3}"
FFMPEG_DOWNLOAD_TIMEOUT="${FFMPEG_DOWNLOAD_TIMEOUT:-15}"
FFMPEG_DOWNLOAD_MAX_TIME="${FFMPEG_DOWNLOAD_MAX_TIME:-600}"
FFMPEG_ARCH_OVERRIDE="${FFMPEG_ARCH_OVERRIDE:-}"

ENABLE_HTTPS="${ENABLE_HTTPS:-false}"
FORCE_HTTPS="${FORCE_HTTPS:-false}"
CERT_FILE="${CERT_FILE:-/etc/ssl/certs/server.crt}"
KEY_FILE="${KEY_FILE:-/etc/ssl/private/server.key}"

echo 'Updating configurations'

# Optional ffmpeg installation
if [ "$ENABLE_FFMPEG" = "true" ]; then
    if command -v ffprobe > /dev/null 2>&1; then
        echo "ffmpeg is already installed."
    else
        echo "Installing ffmpeg from web release..."

        case "$(uname -m)" in
            x86_64|amd64) FFMPEG_ARCH="x64" ;;
            aarch64|arm64) FFMPEG_ARCH="arm64" ;;
            armv7l|armv6l|armhf) FFMPEG_ARCH="arm" ;;
            *)
                echo "ERROR: unsupported architecture for ffmpeg web install: $(uname -m)"
                exit 1
                ;;
        esac
        [ -n "$FFMPEG_ARCH_OVERRIDE" ] && FFMPEG_ARCH="$FFMPEG_ARCH_OVERRIDE"

        BASE_URL="${FFMPEG_BASE_URL:-https://github.com/eugeneware/ffmpeg-static/releases/latest/download}"
        TMP_DIR="$(mktemp -d /tmp/ffmpeg-install.XXXXXX)"
        trap 'rm -rf "$TMP_DIR"' EXIT

        FFMPEG_TMP="$TMP_DIR/ffmpeg"
        FFPROBE_TMP="$TMP_DIR/ffprobe"

        if ! curl -fL --retry "$FFMPEG_DOWNLOAD_RETRIES" --connect-timeout "$FFMPEG_DOWNLOAD_TIMEOUT" --max-time "$FFMPEG_DOWNLOAD_MAX_TIME" "${BASE_URL}/ffmpeg-linux-${FFMPEG_ARCH}" -o "$FFMPEG_TMP"; then
            echo "ERROR: failed to download ffmpeg from ${BASE_URL}/ffmpeg-linux-${FFMPEG_ARCH}"
            exit 1
        fi
        if ! curl -fL --retry "$FFMPEG_DOWNLOAD_RETRIES" --connect-timeout "$FFMPEG_DOWNLOAD_TIMEOUT" --max-time "$FFMPEG_DOWNLOAD_MAX_TIME" "${BASE_URL}/ffprobe-linux-${FFMPEG_ARCH}" -o "$FFPROBE_TMP"; then
            echo "ERROR: failed to download ffprobe from ${BASE_URL}/ffprobe-linux-${FFMPEG_ARCH}"
            exit 1
        fi

        if [ ! -s "$FFMPEG_TMP" ]; then
            echo "ERROR: downloaded ffmpeg file is empty"
            exit 1
        fi
        if [ ! -s "$FFPROBE_TMP" ]; then
            echo "ERROR: downloaded ffprobe file is empty"
            exit 1
        fi

        install -m 0755 "$FFMPEG_TMP" /usr/local/bin/ffmpeg
        install -m 0755 "$FFPROBE_TMP" /usr/local/bin/ffprobe

        ffmpeg -version > /dev/null 2>&1 || {
            echo "ERROR: ffmpeg validation failed after installation"
            exit 1
        }
        ffprobe -version > /dev/null 2>&1 || {
            echo "ERROR: ffprobe validation failed after installation"
            exit 1
        }

        echo "ffmpeg installed successfully."
    fi
else
    echo "Skipping ffmpeg installation."
fi

# Validate certificate files for HTTPS
if [ "$ENABLE_HTTPS" = "true" ]; then
    if [ ! -f "$CERT_FILE" ] || [ ! -f "$KEY_FILE" ]; then
        echo "ERROR: ENABLE_HTTPS=true but certificate files not found!"
        exit 1
    fi
fi

# Create reusable FastCGI configuration file
echo "Creating reusable FastCGI configuration file: /etc/nginx/php-fastcgi.conf"
cat <<'EOF' > /etc/nginx/php-fastcgi.conf
include fastcgi_params;
fastcgi_pass 127.0.0.1:9000;
fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
fastcgi_param REWRITE_ENABLE 1;
fastcgi_buffers 16 32k;
fastcgi_buffer_size 32k;
fastcgi_index index.php;
fastcgi_read_timeout 300s;
fastcgi_send_timeout 300s;
EOF


# Generate Nginx configuration
echo "Generating Nginx configuration..."

# Common locations config
cat <<'EOF' > /etc/nginx/common-locations.conf
autoindex off;

# Block /data except icon directory
location ^~ /data/ {
    deny all;
}
location ^~ /data/icon/ {
    allow all;
}

# Rewrite endpoints
location = / { rewrite ^ /index.php?$query_string last; }
location = /tv.m3u { rewrite ^ /index.php?type=m3u&$query_string last; }
location = /tv.txt { rewrite ^ /index.php?type=txt&$query_string last; }
location = /t.xml { rewrite ^ /index.php?type=xml&$query_string last; }
location = /t.xml.gz { rewrite ^ /index.php?type=gz&$query_string last; }

# PHP FastCGI
location ~ \.php$ {
    include /etc/nginx/php-fastcgi.conf;
}

# Allow larger uploads
client_max_body_size 100M;
EOF


# Use /tmp for all temporary files
if ! grep -q "nginx-client-body" /etc/nginx/nginx.conf; then
    sed -i '/http {/a \
    client_body_temp_path /tmp/nginx-client-body;\n\
    fastcgi_temp_path /tmp/nginx-fastcgi;\n\
    proxy_temp_path /tmp/nginx-proxy;\n\
    uwsgi_temp_path /tmp/nginx-uwsgi;\n\
    scgi_temp_path /tmp/nginx-scgi;\n\
    ' /etc/nginx/nginx.conf
fi

# Build Nginx server blocks
if [ "$ENABLE_HTTPS" = "true" ]; then

    if [ "$FORCE_HTTPS" = "true" ]; then
cat <<EOF > /etc/nginx/http.d/default.conf
server {
    listen ${HTTP_PORT};
    listen [::]:${HTTP_PORT};
    server_name ${SERVER_NAME};
    return 301 https://\$host\$request_uri;
}
EOF
    else
cat <<EOF > /etc/nginx/http.d/default.conf
server {
    listen ${HTTP_PORT};
    listen [::]:${HTTP_PORT};
    server_name ${SERVER_NAME};
    root /htdocs;

    include /etc/nginx/common-locations.conf;

    access_log /dev/null;
    error_log /dev/stderr ${LOG_LEVEL};
}
EOF
    fi

# HTTPS block
cat <<EOF >> /etc/nginx/http.d/default.conf

server {
    listen ${HTTPS_PORT} ssl;
    listen [::]:${HTTPS_PORT} ssl;
    server_name ${SERVER_NAME};

    ssl_certificate     ${CERT_FILE};
    ssl_certificate_key ${KEY_FILE};

    root /htdocs;

    include /etc/nginx/common-locations.conf;

    access_log /dev/null;
    error_log /dev/stderr ${LOG_LEVEL};
}
EOF

else

# HTTP only
cat <<EOF > /etc/nginx/http.d/default.conf
server {
    listen ${HTTP_PORT};
    listen [::]:${HTTP_PORT};
    server_name ${SERVER_NAME};
    root /htdocs;

    include /etc/nginx/common-locations.conf;

    access_log /dev/null;
    error_log /dev/stderr ${LOG_LEVEL};
}
EOF

fi

# IPv6 control (default OFF)
if [ "$ENABLE_IPV6" != "true" ]; then
    echo "IPv6 disabled by configuration, removing IPv6 listen directives"
    sed -i '/listen \[::\]/d' /etc/nginx/http.d/default.conf
fi

# Apply PHP settings
sed -i "s/memory_limit = .*/memory_limit = ${PHP_MEMORY_LIMIT}/" /etc/php83/php.ini
sed -i "s#^;date.timezone =\$#date.timezone = \"${TZ}\"#" /etc/php83/php.ini
sed -i "s/upload_max_filesize = .*/upload_max_filesize = 100M/" /etc/php83/php.ini
sed -i "s/post_max_size = .*/post_max_size = 100M/" /etc/php83/php.ini

# Run PHP-FPM as nginx user
sed -i 's/^user = .*/user = nginx/' /etc/php83/php-fpm.d/www.conf
sed -i 's/^group = .*/group = nginx/' /etc/php83/php-fpm.d/www.conf

# Set system timezone
if [ -e /etc/localtime ]; then rm -f /etc/localtime; fi
ln -s /usr/share/zoneinfo/${TZ} /etc/localtime

echo 'Running cron.php, php-fpm and nginx'

# Change ownership of /htdocs
chown -R nginx:nginx /htdocs

# Change session directory permissions
chmod 1733 /tmp

# Run cron.php as nginx user
if [ -f /htdocs/cron.php ]; then
    cd /htdocs
    su -s /bin/sh -c "php cron.php &" "nginx"
fi

# Start services
memcached -u nobody -d
php-fpm83 -D
exec nginx -g 'daemon off;'
