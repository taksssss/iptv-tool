FROM php:8.2-fpm-alpine
LABEL maintainer="erik.soderblom@gmail.com"
LABEL description="Alpine based image with nginx and php8.2."

# MOD: Tak

# Install essential packages that should be available
RUN apk add --no-cache nginx && mkdir /htdocs

# Install PHP extensions needed for the application
RUN docker-php-ext-install mysqli pdo_mysql

# 复制 ./epg 文件夹内容到 /htdocs
COPY ./epg /htdocs

# Copy nginx configuration
COPY nginx.conf /nginx.conf

EXPOSE 80 443

ADD docker-entrypoint.sh /
RUN chmod +x /docker-entrypoint.sh

ENTRYPOINT ["/docker-entrypoint.sh"]
