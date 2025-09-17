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
cat <<EOF > /etc/nginx/nginx.conf
user nginx;
worker_processes auto;
pid /run/nginx.pid;

events {
    worker_connections 1024;
}

http {
    include /etc/nginx/mime.types;
    default_type application/octet-stream;

    log_format main '\$remote_addr - \$remote_user [\$time_local] "\$request" '
                    '\$status \$body_bytes_sent "\$http_referer" '
                    '"\$http_user_agent" "\$http_x_forwarded_for"';

    access_log /dev/null;
    error_log /dev/stderr ${LOG_LEVEL};

    sendfile on;
    tcp_nopush on;
    tcp_nodelay on;
    keepalive_timeout 65;
    types_hash_max_size 2048;

    server {
        listen ${HTTP_PORT};
        server_name ${HTTP_SERVER_NAME};
        root /htdocs;
        index index.php index.html index.htm;

        # Disable directory listing
        autoindex off;

        # Block access to /htdocs/data except for /htdocs/data/icon
        location /data {
            deny all;
        }

        location /data/icon {
            allow all;
        }

        # URL rewrite rules
        # /tv.m3u
        location = /tv.m3u {
            if (\$args !~ (^|&)type=m3u(&|\$)) {
                rewrite ^.*\$ /index.php?type=m3u&\$args last;
            }
        }

        # /tv.txt
        location = /tv.txt {
            if (\$args !~ (^|&)type=txt(&|\$)) {
                rewrite ^.*\$ /index.php?type=txt&\$args last;
            }
        }

        # /t.xml
        location = /t.xml {
            if (\$args !~ (^|&)type=xml(&|\$)) {
                rewrite ^.*\$ /index.php?type=xml&\$args last;
            }
        }

        # /t.xml.gz
        location = /t.xml.gz {
            if (\$args !~ (^|&)type=gz(&|\$)) {
                rewrite ^.*\$ /index.php?type=gz&\$args last;
            }
        }

        # PHP handling
        location ~ \.php\$ {
            try_files \$uri =404;
            fastcgi_split_path_info ^(.+\.php)(/.+)\$;
            fastcgi_pass 127.0.0.1:9000;
            fastcgi_index index.php;
            include fastcgi_params;
            fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
            fastcgi_param PATH_INFO \$fastcgi_path_info;
        }

        # Static files
        location ~* \.(jpg|jpeg|png|gif|ico|css|js)\$ {
            expires 1y;
            add_header Cache-Control "public, immutable";
        }
    }

    # HTTPS server configuration
    server {
        listen ${HTTPS_PORT} ssl;
        server_name ${HTTPS_SERVER_NAME};
        root /htdocs;
        index index.php index.html index.htm;

        # SSL configuration (you would need to mount certificates)
        # ssl_certificate /path/to/certificate.crt;
        # ssl_certificate_key /path/to/private.key;

        # Disable directory listing
        autoindex off;

        # Block access to /htdocs/data except for /htdocs/data/icon
        location /data {
            deny all;
        }

        location /data/icon {
            allow all;
        }

        # URL rewrite rules (same as HTTP)
        # /tv.m3u
        location = /tv.m3u {
            if (\$args !~ (^|&)type=m3u(&|\$)) {
                rewrite ^.*\$ /index.php?type=m3u&\$args last;
            }
        }

        # /tv.txt
        location = /tv.txt {
            if (\$args !~ (^|&)type=txt(&|\$)) {
                rewrite ^.*\$ /index.php?type=txt&\$args last;
            }
        }

        # /t.xml
        location = /t.xml {
            if (\$args !~ (^|&)type=xml(&|\$)) {
                rewrite ^.*\$ /index.php?type=xml&\$args last;
            }
        }

        # /t.xml.gz
        location = /t.xml.gz {
            if (\$args !~ (^|&)type=gz(&|\$)) {
                rewrite ^.*\$ /index.php?type=gz&\$args last;
            }
        }

        # PHP handling
        location ~ \.php\$ {
            try_files \$uri =404;
            fastcgi_split_path_info ^(.+\.php)(/.+)\$;
            fastcgi_pass 127.0.0.1:9000;
            fastcgi_index index.php;
            include fastcgi_params;
            fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
            fastcgi_param PATH_INFO \$fastcgi_path_info;
        }

        # Static files
        location ~* \.(jpg|jpeg|png|gif|ico|css|js)\$ {
            expires 1y;
            add_header Cache-Control "public, immutable";
        }
    }
}
EOF

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

# Start Memcached, PHP-FPM and Nginx
memcached -u nobody -d
php-fpm -D
nginx -g "daemon off;"