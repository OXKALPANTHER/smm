# Royal SMM — portable PHP + Apache image for Docker hosts
FROM php:8.2-apache

# PDO drivers: MySQL/MariaDB, Postgres (Supabase), SQLite, plus curl for APIs
RUN apt-get update \
    && apt-get install -y libcurl4-openssl-dev libpq-dev libsqlite3-dev \
    && docker-php-ext-install curl pdo pdo_mysql pdo_pgsql pdo_sqlite \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html

# Hosts may inject PORT; otherwise Apache listens on the standard container port 80.
CMD sed -i "s/Listen 80/Listen ${PORT:-80}/" /etc/apache2/ports.conf \
    && sed -i "s/:80>/:${PORT:-80}>/" /etc/apache2/sites-available/000-default.conf \
    && apache2-foreground
