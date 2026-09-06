FROM php:8.3-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git curl libpng-dev libonig-dev libxml2-dev zip unzip nginx \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy semua file
COPY . .

# Install dependencies
RUN composer install --no-interaction --optimize-autoloader --no-dev

RUN php artisan storage:link
RUN php artisan config:cache
RUN php artisan route:cache

# Permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# PHP Upload Limits
RUN echo "upload_max_filesize = 150M" > /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size = 150M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "memory_limit = 256M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "max_execution_time = 300" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "max_input_time = 300" >> /usr/local/etc/php/conf.d/uploads.ini

# Nginx config
RUN echo "server {" > /etc/nginx/sites-available/default \
    && echo "    listen 8080;" >> /etc/nginx/sites-available/default \
    && echo "    server_name _;" >> /etc/nginx/sites-available/default \
    && echo "    root /var/www/html/public;" >> /etc/nginx/sites-available/default \
    && echo "    index index.php;" >> /etc/nginx/sites-available/default \
    && echo "    client_max_body_size 150M;" >> /etc/nginx/sites-available/default \
    && echo "    location / {" >> /etc/nginx/sites-available/default \
    && echo "        try_files \$uri \$uri/ /index.php?\$query_string;" >> /etc/nginx/sites-available/default \
    && echo "    }" >> /etc/nginx/sites-available/default \
    && echo "    location ~ \.php\$ {" >> /etc/nginx/sites-available/default \
    && echo "        fastcgi_pass 127.0.0.1:9000;" >> /etc/nginx/sites-available/default \
    && echo "        fastcgi_index index.php;" >> /etc/nginx/sites-available/default \
    && echo "        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;" >> /etc/nginx/sites-available/default \
    && echo "        include fastcgi_params;" >> /etc/nginx/sites-available/default \
    && echo "    }" >> /etc/nginx/sites-available/default \
    && echo "}" >> /etc/nginx/sites-available/default \
    && rm /etc/nginx/sites-enabled/default \
    && ln -s /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default

EXPOSE 8080

# Start dengan key:generate di runtime
CMD ["sh", "-c", "php artisan key:generate --force && php-fpm -D && nginx -g 'daemon off;'"]