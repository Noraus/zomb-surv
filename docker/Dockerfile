FROM php:8.4-cli-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends libpq-dev libicu-dev libzip-dev libonig-dev libxml2-dev unzip git && docker-php-ext-install -j4 pdo_pgsql intl zip mbstring dom xml xmlwriter && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /app
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
