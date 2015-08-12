FROM debian:wheezy

ENV DEBIAN_FRONTEND noninteractive

# sources

RUN apt-key adv --keyserver keyserver.ubuntu.com --recv-keys E9C74FEEA2098A6E && \
    echo "deb http://packages.dotdeb.org/ wheezy-php56 all" > /etc/apt/sources.list.d/php.list && \
    apt-get clean && apt-get update -y

# installation

RUN apt-get install -y --no-install-recommends \
    curl \
    syslogd \
    bash-completion \
    supervisor \
    locales \
    ca-certificates \
    vim \
    nginx-light \
    php5-fpm \
    php5-cli \
    php5-xdebug \
    php5-mongo

# locale

RUN echo "pl_PL.UTF-8 UTF-8" >> /etc/locale.gen && \
    echo "Europe/Warsaw" > /etc/timezone && \
    dpkg-reconfigure -f noninteractive tzdata && \
    locale-gen --purge

# tweaks

RUN sed -e 's/;daemonize = yes/daemonize = no/' -i /etc/php5/fpm/php-fpm.conf && \
    sed -e 's/;listen\.owner/listen.owner/' -i /etc/php5/fpm/pool.d/www.conf && \
    sed -e 's/;listen\.group/listen.group/' -i /etc/php5/fpm/pool.d/www.conf && \
    echo "\ndaemon off;" >> /etc/nginx/nginx.conf && \
    echo "xdebug.cli_color=1" >> /etc/php5/mods-available/xdebug.ini;

# composer

RUN curl -sS https://getcomposer.org/installer | php  && \
    mv composer.phar /usr/local/bin/composer.phar

# configs

COPY ./conf/vhost.conf /etc/nginx/sites-available/default
COPY ./conf/supervisor.conf /etc/supervisor/conf.d/supervisor.conf
COPY ./scripts/init.sh /init.sh

EXPOSE 80

VOLUME ["/data", "/collection"]

WORKDIR /data

CMD ["/usr/bin/supervisord"]