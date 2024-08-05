FROM php:8.1-fpm

# Create www-data user if it doesn't exist
RUN set -x \
    && (groupadd -g 82 www-data || true) \
    && (useradd -u 82 -g www-data www-data || true)

# Install dependencies
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libxml2-dev \
    libzip-dev \
    libpq-dev \
    libexif-dev \
    git \
    unzip

# Install PHP extensions
RUN docker-php-ext-install gd soap zip intl pgsql pdo_pgsql exif

# Set the working directory
WORKDIR /var/www/html

# Clone Moodle repository
RUN git clone -b MOODLE_404_STABLE git://git.moodle.org/moodle.git .

# Set permissions
RUN chown -R www-data:www-data /var/www/html 

# Create moodledata directory and set permissions
RUN mkdir -p /var/www/moodledata && \
    chown -R www-data:www-data /var/www/moodledata && \
    chmod 777 /var/www/moodledata

COPY www.conf /usr/local/etc/php-fpm.d/www.conf

# Configure PHP settings
RUN echo "max_input_vars = 5000" >> /usr/local/etc/php/conf.d/docker-php-ext-max-input-vars.ini
RUN echo "memory_limit = 512M" >> /usr/local/etc/php/conf.d/docker-php-memlimit.ini

# Enable and configure opcache
RUN docker-php-ext-enable opcache && \
    echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini && \
    echo "opcache.enable_cli=1" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini && \
    echo "opcache.memory_consumption=128" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini && \
    echo "opcache.interned_strings_buffer=8" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini && \
    echo "opcache.max_accelerated_files=4000" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini && \
    echo "opcache.revalidate_freq=60" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini && \
    echo "opcache.fast_shutdown=1" >> /usr/local/etc/php/conf.d/docker-php-ext-opcache.ini

# Create and set permissions for the shared temp directory
RUN mkdir -p /var/www/moodledata/temp/multimodal_files && \
    chown -R www-data:www-data /var/www/moodledata/temp/multimodal_files && \
    chmod -R 777 /var/www/moodledata/temp/multimodal_files

# Add these lines near the end of your Moodle Dockerfile, before the CMD instruction
RUN mkdir -p /var/www/moodledata/temp/filestorage && \
    chown -R www-data:www-data /var/www/moodledata && \
    chmod -R 0777 /var/www/moodledata

# Expose port 9000 for PHP-FPM
EXPOSE 9000