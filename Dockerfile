FROM php:8.2-apache

# Instala extensão PDO para MySQL
RUN docker-php-ext-install pdo pdo_mysql

# Habilita mod_rewrite do Apache
RUN a2enmod rewrite

# Copia todos os arquivos do projeto para o DocumentRoot do Apache
COPY . /var/www/html/

# Garante permissões corretas
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Configuração do Apache: permite .htaccess e acesso ao diretório
RUN echo '<Directory /var/www/html>\n\
    Options Indexes FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' > /etc/apache2/conf-available/esgp.conf \
    && a2enconf esgp

EXPOSE 80
