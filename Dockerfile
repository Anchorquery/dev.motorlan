# Usar la última imagen de WordPress con PHP 8.2 y Apache
FROM wordpress:php8.2-apache

# Permalinks
RUN set -eux; a2enmod rewrite; \
    apt-get update && apt-get install -y --no-install-recommends curl && \
    rm -rf /var/lib/apt/lists/*

# ⚠️ Evita sobreescribir el .htaccess de WP salvo que sea imprescindible
# COPY .htaccess /var/www/html/.htaccess

# Copia solo lo que controlas (temas/plugins/mu-plugins)
COPY wp-content /var/www/html/wp-content
RUN chown -R www-data:www-data /var/www/html/wp-content

EXPOSE 80

# Healthcheck por HTTP
HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=10 \
  CMD curl -fsS http://localhost/ || exit 1