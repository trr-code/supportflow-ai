# syntax=docker/dockerfile:1
FROM dunglas/frankenphp:1.13.6-php8.5.13-bookworm@sha256:e673b1932022450620304b18543d9437205fb5ded3daf25fadf7d777fa4cd0b8 AS dependencies
RUN install-php-extensions pcntl pdo_pgsql pgsql intl zip bcmath gd exif sockets redis
COPY --from=composer:2.11.2@sha256:cd3a90dc5624c59a2f9ddf80dad08569f06416db31de519024f249d63bca554b /usr/bin/composer /usr/local/bin/composer
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist

FROM node:22.23.4-bookworm-slim@sha256:5ec23b7c622b00547753bc6ffd78085dd868a68aeb41c8e0a1418f033e13d96d AS assets
WORKDIR /app
COPY package.json package-lock.json .npmrc ./
RUN npm ci
COPY resources resources
COPY --from=dependencies /app/vendor vendor
COPY vite.config.js ./
RUN npm run build

FROM dependencies AS runtime
COPY . .
COPY --from=assets /app/public/build public/build
COPY docker-php.ini /usr/local/etc/php/conf.d/zz-lucid.ini
RUN mkdir -p storage/app/private storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && composer dump-autoload --no-dev --optimize --no-interaction \
    && cp vendor/laravel/octane/src/Commands/stubs/frankenphp-worker.php public/frankenphp-worker.php \
    && chown -R www-data:www-data storage bootstrap/cache
USER www-data
HEALTHCHECK NONE
ENTRYPOINT ["php", "artisan"]
CMD ["octane:start", "--server=frankenphp", "--host=0.0.0.0", "--port=8001", "--admin-port=2019", "--workers=1", "--max-requests=500"]

FROM dependencies AS test-tools
COPY --from=assets /usr/local/bin/node /usr/local/bin/node
COPY --from=assets /usr/local/lib/node_modules/npm /usr/local/lib/node_modules/npm
COPY --from=assets /app/node_modules /app/node_modules
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -s /usr/local/lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx \
    && composer install --no-scripts --no-autoloader --no-interaction --prefer-dist \
    && npx playwright install --with-deps chromium

FROM test-tools AS test
COPY . .
COPY --from=assets /app/public/build public/build
COPY docker-php.ini /usr/local/etc/php/conf.d/zz-lucid.ini
RUN mkdir -p storage/app/private storage/app/public storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache /test-locks \
    && cp .env.example .env \
    && composer dump-autoload --optimize --no-interaction \
    && sed -e 's/name="DB_HOST" value="127.0.0.1"/name="DB_HOST" value="postgres-test"/' \
           -e 's/name="DB_USERNAME" value="root"/name="DB_USERNAME" value="testing"/' \
           -e 's/name="DB_PASSWORD" value=""/name="DB_PASSWORD" value="testing"/' phpunit.xml > phpunit.docker.xml \
    && mv phpunit.docker.xml phpunit.xml \
    && chmod 1777 /test-locks
HEALTHCHECK NONE
