FROM php:8.4-cli

ARG WWWGROUP=1000
ARG NODE_VERSION=20

WORKDIR /var/www/html

ENV COMPOSER_ALLOW_SUPERUSER=1
ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl \
        git \
        unzip \
        pkg-config \
        libzip-dev \
        libicu-dev \
        libonig-dev \
        libxml2-dev \
        libssl-dev \
        mariadb-client \
        default-mysql-client \
        gosu \
    && pecl install mongodb \
    && docker-php-ext-enable mongodb \
    && docker-php-ext-install intl pdo_mysql zip \
    && curl -fsSL https://deb.nodesource.com/setup_${NODE_VERSION}.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && npm install -g npm \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && groupadd --force -g ${WWWGROUP} sail \
    && useradd -ms /bin/bash --no-user-group -g ${WWWGROUP} -u 1337 sail \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY docker/start-container.sh /usr/local/bin/start-container
RUN chmod +x /usr/local/bin/start-container

COPY docker/test-runner.sh /usr/local/bin/test-runner
RUN chmod +x /usr/local/bin/test-runner

EXPOSE 8000 5173

CMD ["start-container"]
