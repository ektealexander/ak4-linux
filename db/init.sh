set -e

if [ ! -d /var/lib/mysql/mysql ]; then
    echo "Initialiserer MariaDB database i /var/lib/mysql..."
    mysql_install_db --user=mysql --datadir=/var/lib/mysql --skip-test-db
fi

exec mysqld --user=mysql --datadir=/var/lib/mysql