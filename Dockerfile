# syntax=docker/dockerfile:1

FROM ghcr.io/linuxserver/baseimage-alpine-nginx:3.23

# set version label
ARG BUILD_DATE
ARG VERSION
ARG BOOKSTACK_RELEASE
LABEL build_version="Linuxserver.io version:- ${VERSION} Build-date:- ${BUILD_DATE}"
LABEL maintainer="thespad"

ENV S6_STAGE2_HOOK="/init-hook"

RUN \
  echo "**** install runtime packages ****" && \
  apk add --no-cache \
    fontconfig \
    mariadb-client \
    memcached \
    php85-dom \
    php85-exif \
    php85-gd \
    php85-ldap \
    php85-mysqlnd \
    php85-pdo_mysql \
    php85-pecl-memcached \
    php85-tokenizer \
    qt5-qtbase \
    ttf-freefont && \
  echo "**** configure php-fpm to pass env vars ****" && \
  sed -E -i 's/^;?clear_env ?=.*$/clear_env = no/g' /etc/php85/php-fpm.d/www.conf && \
  if ! grep -qxF 'clear_env = no' /etc/php85/php-fpm.d/www.conf; then echo 'clear_env = no' >> /etc/php85/php-fpm.d/www.conf; fi && \
  echo "env[PATH] = /usr/local/bin:/usr/bin:/bin" >> /etc/php85/php-fpm.conf && \
  mkdir -p /app/www && \
  printf "Linuxserver.io version: ${VERSION}\nBuild-date: ${BUILD_DATE}" > /build_version

# Copy local BookStack source
COPY . /app/www/

# Install node, build frontend assets, then remove node
RUN \
  echo "**** install nodejs for building assets ****" && \
  apk add --no-cache --virtual .build-deps nodejs npm && \
  echo "**** build frontend assets ****" && \
  cd /app/www && \
  npm ci && \
  npm run production && \
  echo "**** cleanup node dependencies ****" && \
  rm -rf node_modules && \
  apk del .build-deps

# Install composer dependencies and create symlinks
RUN \
  echo "**** install composer dependencies ****" && \
  composer install -d /app/www/ --no-dev --optimize-autoloader && \
  echo "**** backup themes before symlinking ****" && \
  cp -r /app/www/themes /tmp/themes-backup && \
  echo "**** create symlinks ****" && \
  /bin/bash -c \
  'dst=(www/themes www/files www/images www/uploads backups www/framework/cache www/framework/sessions www/framework/views log/bookstack/laravel.log www/.env); \
  src=(themes storage/uploads/files storage/uploads/images public/uploads storage/backups storage/framework/cache storage/framework/sessions storage/framework/views storage/logs/laravel.log .env); \
  for i in "${!src[@]}"; do rm -rf /app/www/"${src[i]}" && ln -s /config/"${dst[i]}" /app/www/"${src[i]}"; done' && \
  echo "**** restore themes to config location ****" && \
  mkdir -p /config/www/themes && \
  cp -r /tmp/themes-backup/* /config/www/themes/ 2>/dev/null || true && \
  echo "**** cleanup ****" && \
  rm -rf \
    /tmp/* \
    $HOME/.cache \
    $HOME/.composer

# copy local files
COPY root/ /

# ports and volumes
EXPOSE 80 443
VOLUME /config
