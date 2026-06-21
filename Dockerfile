# Royal SMM — PHP + Apache image (works on Render, Fly.io, Koyeb, Cloud Run)
FROM php:8.2-apache

# PDO drivers: Postgres (Supabase) + SQLite, plus curl for the Boost API
RUN apt-get update \
    && apt-get install -y libpq-dev libsqlite3-dev \
    && docker-php-ext-install pdo pdo_pgsql pdo_sqlite \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html

# Many hosts inject the port via $PORT (Render uses 10000). Bind Apache to it.
CMD sed -i "s/Listen 80/Listen ${PORT:-80}/" /etc/apache2/ports.conf \
    && sed -i "s/:80>/:${PORT:-80}>/" /etc/apache2/sites-available/000-default.conf \
    && apache2-foreground
