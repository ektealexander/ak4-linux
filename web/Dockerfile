FROM alpine:latest

RUN apk update && apk add apache2

RUN echo "ServerName localhost" >> /etc/apache2/httpd.conf

COPY index.php /var/www/localhost/htdocs/index.php

EXPOSE 80

CMD ["httpd", "-D", "FOREGROUND"]