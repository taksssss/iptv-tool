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
            x86_64|amd64) FFMPEG_ARCH="linux64" ;;
            aarch64|arm64) FFMPEG_ARCH="linuxarm64" ;;
            *)
                echo "ERROR: unsupported architecture for ffmpeg web install: $(uname -m)"
                exit 1
                ;;
        esac

        RELEASE_API="https://api.github.com/repos/BtbN/FFmpeg-Builds/releases/latest"
        RELEASE_JSON="$(curl -fsSL "$RELEASE_API")"
        FFMPEG_URL="$(printf '%s\n' "$RELEASE_JSON" | grep -Eo "https://[^\"]*ffmpeg-N-[^\"]*-${FFMPEG_ARCH}-gpl\.tar\.xz" | head -n 1 || true)"
        if [ -z "$FFMPEG_URL" ]; then
            FFMPEG_URL="$(printf '%s\n' "$RELEASE_JSON" | grep -Eo "https://[^\"]*ffmpeg-[^\"]*-${FFMPEG_ARCH}-gpl\.tar\.xz" | grep -v 'shared' | head -n 1 || true)"
        fi

        if [ -z "$FFMPEG_URL" ]; then
            echo "ERROR: cannot find ffmpeg download url for architecture: $FFMPEG_ARCH"
            exit 1
        fi

        TMP_DIR="$(mktemp -d /tmp/ffmpeg-install.XXXXXX)"
        trap 'rm -rf "$TMP_DIR"' EXIT

        ARCHIVE_PATH="$TMP_DIR/ffmpeg.tar.xz"
        curl -fL "$FFMPEG_URL" -o "$ARCHIVE_PATH"
        tar -xJf "$ARCHIVE_PATH" -C "$TMP_DIR"

        FFMPEG_BIN="$(find "$TMP_DIR" -type f -name ffmpeg | head -n 1)"
        FFPROBE_BIN="$(find "$TMP_DIR" -type f -name ffprobe | head -n 1)"

        if [ -z "$FFMPEG_BIN" ] || [ -z "$FFPROBE_BIN" ]; then
            echo "ERROR: ffmpeg or ffprobe binary not found in downloaded archive"
            exit 1
        fi

        install -m 0755 "$FFMPEG_BIN" /usr/local/bin/ffmpeg
        install -m 0755 "$FFPROBE_BIN" /usr/local/bin/ffprobe

        ffprobe -version > /dev/null 2>&1 || {
            echo "ERROR: ffmpeg installed but ffprobe check failed"
            exit 1
        }

        rm -rf "$TMP_DIR"
        trap - EXIT
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
