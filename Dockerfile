FROM php:8.4-fpm

ARG WWWGROUP=1000
ARG NODE_VERSION=20

WORKDIR /var/www/html

ENV COMPOSER_ALLOW_SUPERUSER=1
ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        build-essential \
        autoconf \
        pkg-config \
        libtool \
        make \
        gcc \
        g++ \
        curl \
        git \
        unzip \
        libzip-dev \
        libicu-dev \
        libonig-dev \
        libxml2-dev \
        libssl-dev \
        libsasl2-dev \
        libcurl4-openssl-dev \
        mariadb-client \
        default-mysql-client \
        nginx \
        gosu \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install intl pdo_mysql zip opcache

RUN curl -fsSL https://deb.nodesource.com/setup_${NODE_VERSION}.x | bash - \
    && apt-get update \
    && apt-get install -y --no-install-recommends nodejs \
    && npm install -g npm \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && groupadd --force -g ${WWWGROUP} sail \
    && useradd -ms /bin/bash --no-user-group -g ${WWWGROUP} -u 1337 sail

COPY docker/nginx.conf /etc/nginx/sites-enabled/default
COPY docker/php.ini $PHP_INI_DIR/conf.d/99-opcache.ini

COPY docker/start-container.sh /usr/local/bin/start-container
RUN chmod +x /usr/local/bin/start-container

COPY docker/test-runner.sh /usr/local/bin/test-runner
RUN chmod +x /usr/local/bin/test-runner

EXPOSE 8000 5173

CMD ["start-container"]
