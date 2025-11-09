FROM ubuntu:22.04

ENV DEBIAN_FRONTEND=noninteractive
RUN apt-get update && apt-get install -y --no-install-recommends \
    mariadb-server \
    mariadb-client \
    ca-certificates \
    curl \
 && rm -rf /var/lib/apt/lists/*

COPY init.sh /usr/local/bin/init.sh
RUN sed -i 's/\r$//' /usr/local/bin/init.sh && chmod +x /usr/local/bin/init.sh

VOLUME ["/var/lib/mysql"]
EXPOSE 3306
CMD ["/usr/local/bin/init.sh"]