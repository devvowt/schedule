# Imagem base do serviço: PHP CLI com as extensões usadas pelo agendador.
# O alvo "octane" adiciona o Swoole, necessário para os task workers que rodam
# as tarefas marcadas como paralelas.
FROM php:8.4-cli AS base

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        procps \
        default-mysql-client \
        libzip-dev \
        libicu-dev \
        libssl-dev \
    && docker-php-ext-install pdo_mysql zip intl bcmath opcache pcntl sockets \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-scheduler.ini
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

WORKDIR /var/www/html

ENTRYPOINT ["entrypoint"]

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]


# ---------------------------------------------------------------------------
FROM base AS octane

# Build enxuto do Swoole: só o servidor HTTP e os task workers são usados aqui.
# "yes no" responde "não" a todas as extensões opcionais (openssl, http2,
# curl-hook, pgsql...), que não fazem falta no tráfego interno do agendador.
RUN yes 'no' | pecl install swoole \
    && docker-php-ext-enable swoole

CMD ["php", "artisan", "octane:start", "--server=swoole", "--host=0.0.0.0", "--port=8000"]
