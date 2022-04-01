FROM php:8-apache
WORKDIR /var/www/html
RUN a2enmod rewrite headers && \
    apt-get update && apt-get install -y \
    zip && \
    echo "expose_php = off" >> /usr/local/etc/php/php.ini && \
    sed -i 's/ServerTokens OS/ServerTokens Prod/g' /etc/apache2/conf-available/security.conf && \
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
COPY src/composer.json src/composer.lock ./
RUN composer install && rm composer.json composer.lock
COPY src ./
