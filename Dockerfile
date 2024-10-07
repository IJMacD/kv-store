FROM php:8.1-apache
WORKDIR /var/www/html
RUN apt-get update && apt-get install -y \
    libpq-dev \
    zip && \
    rm -rf /var/lib/apt/lists/*
RUN a2enmod rewrite headers && \
    echo "expose_php = off" >> /usr/local/etc/php/php.ini && \
    sed -i 's/ServerTokens OS/ServerTokens Prod/g' /etc/apache2/conf-available/security.conf && \
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer && \
    docker-php-ext-install pdo_mysql pdo_pgsql
COPY src/composer.json src/composer.lock ./
RUN composer install && rm composer.json composer.lock
COPY src ./
