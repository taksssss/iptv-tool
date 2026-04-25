FROM alpine:3.20
LABEL maintainer="decat2008@gmail.com"
LABEL description="Alpine based image with nginx and php8.3-fpm."

# 使用中科大镜像（改用GitHub Actions，无需镜像）
# RUN sed -i 's/dl-cdn.alpinelinux.org/mirrors.ustc.edu.cn/g' /etc/apk/repositories

# 安装常用扩展
RUN apk --no-cache --update \
    add nginx \
    curl \
    icu-data-full \
    memcached \
    tzdata \
    php83-bcmath \
    php83-bz2 \
    php83-calendar \
    php83-cli \
    php83-common \
    php83-ctype \
    php83-curl \
    php83-dom \
    php83-gd \
    php83-fpm \
    php83-iconv \
    php83-intl \
    php83-json \
    php83-mbstring \
    php83-mysqli \
    php83-mysqlnd \
    php83-openssl \
    php83-pdo_mysql \
    php83-pdo_pgsql \
    php83-pdo_sqlite \
    php83-pecl-memcached \
    php83-pecl-redis \
    php83-phar \
    php83-session \
    php83-simplexml \
    php83-xml \
    php83-xmlreader \
    php83-xmlwriter \
    php83-posix \
    php83-zip \
    && mkdir -p /htdocs /run/nginx

# 复制 ./epg 文件夹内容到 /htdocs
COPY ./epg /htdocs

EXPOSE 80 443

ADD docker-entrypoint.sh /
RUN chmod +x /docker-entrypoint.sh

ENTRYPOINT ["/docker-entrypoint.sh"]