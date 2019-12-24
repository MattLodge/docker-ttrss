FROM php:7.3-apache
MAINTAINER Christian Lück <christian@lueck.tv>
MAINTAINER James Cole <thegrumpydictator@gmail.com>

RUN DEBIAN_FRONTEND=noninteractive apt-get update && apt-get install -y \
   nginx supervisor libmcrypt-dev libpng-dev curl zlib1g-dev libicu-dev g++ && \
   apt-get clean && rm -rf /var/lib/apt/lists/* && pecl install mcrypt-1.0.3 && docker-php-ext-enable mcrypt && \
   docker-php-ext-install -j$(nproc) gd json pdo pdo_mysql intl mysqli pcntl

COPY ./ttrss.apache.conf /etc/apache2/sites-available/000-default.conf

# install ttrss and patch configuration
WORKDIR /var/www
RUN curl -SL https://git.tt-rss.org/fox/tt-rss/archive/master.tar.gz | tar xzC /var/www --strip-components 1 \
    && chown www-data:www-data -R /var/www && cp config.php-dist config.php

# expose only Apache HTTP port
EXPOSE 80

# always re-configure database with current ENV when RUNning container, then monitor all services
ADD configure-db.php /configure-db.php
ADD supervisord.conf /etc/supervisor/conf.d/supervisord.conf
CMD php /configure-db.php && supervisord -c /etc/supervisor/conf.d/supervisord.conf
