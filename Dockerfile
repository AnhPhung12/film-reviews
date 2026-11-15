FROM php:8.2-apache

RUN apt-get update && apt-get install -y libpng-dev libjpeg-dev libwebp-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install pdo_mysql gd \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY CW /var/www/html/COMP1841/CW
RUN printf '<?php header("Location: /COMP1841/CW/"); exit;\n' > /var/www/html/index.php
RUN sed -i 's/80/${PORT}/g' /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf
