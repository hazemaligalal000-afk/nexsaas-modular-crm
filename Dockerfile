FROM php:8.1-apache-bullseye

# Install required extensions for Vtiger CRM (Imap, GD, mysqli, curl, zip, PDO, etc.)
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libcurl4-openssl-dev \
    libc-client-dev \
    libkrb5-dev \
    unzip \
    cron \
    && rm -r /var/lib/apt/lists/*

# Configure and install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-configure imap --with-kerberos --with-imap-ssl \
    && docker-php-ext-install gd imap mysqli pdo pdo_mysql curl zip

# Create customized php.ini for optimal CRM execution (Step 12: Performance)
RUN echo "memory_limit = 512M" > /usr/local/etc/php/conf.d/vtiger.ini \
    && echo "max_execution_time = 300" >> /usr/local/etc/php/conf.d/vtiger.ini \
    && echo "error_reporting = E_WARNING & ~E_NOTICE & ~E_DEPRECATED" >> /usr/local/etc/php/conf.d/vtiger.ini \
    && echo "display_errors = Off" >> /usr/local/etc/php/conf.d/vtiger.ini \
    && echo "log_errors = On" >> /usr/local/etc/php/conf.d/vtiger.ini \
    && echo "short_open_tag = On" >> /usr/local/etc/php/conf.d/vtiger.ini \
    && echo "post_max_size = 50M" >> /usr/local/etc/php/conf.d/vtiger.ini \
    && echo "upload_max_filesize = 50M" >> /usr/local/etc/php/conf.d/vtiger.ini

# Enable Apache mod_rewrite for API & modern CRM routing
RUN a2enmod rewrite

# Setup CRM Directory permissions
WORKDIR /var/www/html
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
CMD ["apache2-foreground"]
