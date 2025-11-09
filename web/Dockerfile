FROM ubuntu:22.04

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update && apt-get install -y --no-install-recommends \
    apache2 \
    php \
    php-cli \
    libapache2-mod-php \
    php-mysql \
    php-json \
    ca-certificates \
    curl \
 && rm -rf /var/lib/apt/lists/*

RUN a2enmod php* && a2enmod rewrite

WORKDIR /var/www/html

COPY index.php .
COPY config.php .

RUN echo "ServerName localhost" > /etc/apache2/conf-available/servername.conf \
 && a2enconf servername \
 && sed -i 's/DirectoryIndex .*/DirectoryIndex index.php index.html/' /etc/apache2/mods-enabled/dir.conf \
 && mkdir -p /var/log/apache2 \
 && chown -R www-data:www-data /var/www/html

EXPOSE 80
CMD ["apachectl", "-D", "FOREGROUND"]