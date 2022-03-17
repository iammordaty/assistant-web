FROM php:8.1-fpm

ENV DEBIAN_FRONTEND=noninteractive
ENV TERM=xterm

# dependencies

RUN apt-get update && \
    apt-get install -y --no-install-recommends \
    curl \
    libcurl4-gnutls-dev \
    libicu-dev \
    libssl-dev \
    libzip-dev \
    locales \
    ssh-client \
    sshpass \
    unzip \
    vim;

# PHP extensions

RUN pecl install inotify && \
    docker-php-ext-enable inotify

RUN pecl install mongodb && \
    docker-php-ext-enable mongodb

RUN pecl install xdebug && \
    docker-php-ext-enable xdebug

RUN docker-php-ext-install \
    intl \
    opcache \
    zip

RUN apt-get -y autoremove && \
    apt-get -y clean

WORKDIR /data

VOLUME /collection
VOLUME /data
VOLUME /metadata

COPY ./conf/zzz-docker.conf /usr/local/etc/php-fpm.d/zzz-docker.conf

EXPOSE 9000

# locale

RUN echo "pl_PL.UTF-8 UTF-8" >> /etc/locale.gen && \
    echo "Europe/Warsaw" > /etc/timezone && \
    dpkg-reconfigure -f noninteractive tzdata && \
    locale-gen --purge

ENV LANG pl_PL.utf8

# apps

COPY ./bin/essentia_streaming_extractor_music /usr/local/bin/
COPY ./bin/musly /usr/local/bin/

RUN chmod +x /usr/local/bin/essentia_streaming_extractor_music /usr/local/bin/musly

# composer

ENV COMPOSER_ALLOW_SUPERUSER=1

RUN curl -sS https://getcomposer.org/installer | php && \
    mv composer.phar /usr/local/bin/composer

CMD [ "php-fpm", "-R" ]
