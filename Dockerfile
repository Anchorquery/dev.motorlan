# WordPress + PHP 8.2 + Apache
FROM wordpress:6.6.1-php8.2-apache

# Permalinks
RUN a2enmod rewrite

# (Opcional) copia .htaccess si de verdad necesitas reglas propias
# OJO: si no lo necesitas, comenta la siguiente línea para no pisar el que genera WP
COPY .htaccess /var/www/html/.htaccess

# Copia SOLO lo que controlas (temas/plugins/mu-plugins). Evita subir uploads si no es necesario.
COPY wp-content /var/www/html/wp-content

# Permisos
RUN chown -R www-data:www-data /var/www/html/wp-content

# Exponer el puerto REAL de Apache
EXPOSE 80

# (Opcional) healthcheck básico
HEALTHCHECK --interval=30s --timeout=5s --retries=10 CMD curl -fsS http://localhost/ || exit 1
