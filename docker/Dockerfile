# Taste Alchemy · PHP 8.1 + Apache + pdo_mysql + mbstring
FROM php:8.1-apache

# Extensiones que necesita la aplicación. mbstring requiere oniguruma.
RUN apt-get update \
    && apt-get install -y --no-install-recommends libonig-dev \
    && docker-php-ext-install pdo_mysql mbstring \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

# VirtualHost con el front controller en public/.
COPY config/apache-vhost.conf /etc/apache2/sites-available/000-default.conf

# Código de la aplicación.
COPY . /var/www/html
RUN mkdir -p /var/www/html/var/log /var/www/html/var/run \
    && chown -R www-data:www-data /var/www/html/var

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www/html
EXPOSE 80

ENTRYPOINT ["entrypoint.sh"]