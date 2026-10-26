FROM php:8.3-apache

RUN docker-php-ext-install pdo_sqlite \
    && a2enmod rewrite \
    && printf '<Directory /var/www/html>\n    AllowOverride All\n    Require all granted\n</Directory>\n' \
        > /etc/apache2/conf-available/admission-portal.conf \
    && a2enconf admission-portal

WORKDIR /var/www/html
COPY . /var/www/html
COPY docker/start.sh /usr/local/bin/start-admission-portal
RUN chmod +x /usr/local/bin/start-admission-portal

EXPOSE 10000
CMD ["/usr/local/bin/start-admission-portal"]
