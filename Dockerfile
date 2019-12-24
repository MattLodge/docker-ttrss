FROM php:7.3-apache
MAINTAINER Christian Lück <christian@lueck.tv>
MAINTAINER James Cole <thegrumpydictator@gmail.com>

#RUN DEBIAN_FRONTEND=noninteractive apt-get update && apt-get install -y \
#  nginx supervisor php7.3-fpm php7.3-cli php7.3-curl php7.3-gd php7.3-json \
#  php7.3-pgsql php7.3-mysql php7.3-mcrypt && apt-get clean && rm -rf /var/lib/apt/lists/*



RUN DEBIAN_FRONTEND=noninteractive apt-get update && apt-get install -y \
   nginx supervisor libmcrypt-dev libpng-dev curl zlib1g-dev sudo  libicu-dev g++ && \
   apt-get clean && rm -rf /var/lib/apt/lists/*

# enable the mcrypt module
RUN pecl install mcrypt-1.0.3 && docker-php-ext-enable mcrypt

# other modules.
RUN docker-php-ext-install -j$(nproc) gd json pdo pdo_mysql intl mysqli pcntl

# add ttrss as the only nginx site
# ADD ttrss.nginx.conf /etc/nginx/sites-available/ttrss
# RUN ln -s /etc/nginx/sites-available/ttrss /etc/nginx/sites-enabled/ttrss
# RUN rm /etc/nginx/sites-enabled/default

COPY ./ttrss.apache.conf /etc/apache2/sites-available/000-default.conf

# install ttrss and patch configuration
WORKDIR /var/www
RUN apt-get update && DEBIAN_FRONTEND=noninteractive apt-get install -y curl --no-install-recommends && rm -rf /var/lib/apt/lists/* \
    && curl -SL https://git.tt-rss.org/fox/tt-rss/archive/master.tar.gz | tar xzC /var/www --strip-components 1 \
    && apt-get purge -y --auto-remove curl \
    && chown www-data:www-data -R /var/www

RUN cp config.php-dist config.php

# expose only nginx HTTP port
EXPOSE 80

# always re-configure database with current ENV when RUNning container, then monitor all services
ADD configure-db.php /configure-db.php
ADD supervisord.conf /etc/supervisor/conf.d/supervisord.conf
CMD php /configure-db.php && supervisord -c /etc/supervisor/conf.d/supervisord.conf
