FROM wordpress:6.9-php8.2-fpm

# OPcache is included in the base image; just configure it.
# mysqli/pdo_mysql are also included.

# Install WP-CLI for seeding
RUN curl -sL https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar -o /usr/local/bin/wp \
    && chmod +x /usr/local/bin/wp

# PHP-FPM pool config (4 static workers by default — predictable, no fork noise)
COPY www.conf /usr/local/etc/php-fpm.d/zz-perf.conf

# PHP config (OPcache on, generous limits for WooCommerce)
COPY php.ini /usr/local/etc/php/conf.d/99-perf.ini

# Healthcheck script for PHP-FPM
RUN echo '#!/bin/sh' > /usr/local/bin/php-fpm-healthcheck \
    && echo 'SCRIPT_NAME=/ping SCRIPT_FILENAME=/ping REQUEST_METHOD=GET cgi-fcgi -bind -connect 127.0.0.1:9000 2>/dev/null | grep -q pong' >> /usr/local/bin/php-fpm-healthcheck \
    && chmod +x /usr/local/bin/php-fpm-healthcheck \
    || true

# Install fcgi for healthcheck (if available)
RUN apt-get update -qq && apt-get install -y -qq libfcgi-bin 2>/dev/null || true && rm -rf /var/lib/apt/lists/*
