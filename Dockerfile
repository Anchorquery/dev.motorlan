# Use the official WordPress image with PHP 8.2 and Apache
FROM wordpress:6.6.1-php8.2-apache

# Enable Apache rewrite module for permalinks
RUN a2enmod rewrite

# Copy the .htaccess file
COPY .htaccess /var/www/html/.htaccess

# Copy the wp-content directory
COPY wp-content /var/www/html/wp-content

# Set the correct permissions for wp-content
RUN chown -R www-data:www-data /var/www/html/wp-content

# Expose port 80 for the Apache web server
EXPOSE 8030
