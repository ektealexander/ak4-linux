set -euo pipefail

DATADIR="/var/lib/mysql"
SOCKET="/run/mysqld/mysqld.sock"
INIT_FLAG="$DATADIR/.ak4-init-complete"
mkdir -p /run/mysqld
chown -R mysql:mysql /run/mysqld "$DATADIR"

echo "[init] start mysqld for initialisering..."
mysqld --datadir="$DATADIR" --user=mysql --socket="$SOCKET" --bind-address=127.0.0.1 &
MYSQLD_PID=$!

echo "[init] venter på at MariaDB starter..."
for i in {1..60}; do
  mysqladmin --protocol=socket --socket="$SOCKET" -uroot ping >/dev/null 2>&1 && break
  sleep 1
done

if ! mysqladmin --protocol=socket --socket="$SOCKET" -uroot ping >/dev/null 2>&1; then
  echo "[init] FEIL: MariaDB startet ikke!" >&2
  exit 1
fi

if [ ! -f "$INIT_FLAG" ]; then
  echo "[init] første gangs initialisering..."
  
  ROOT_PASS="${MYSQL_ROOT_PASSWORD:-rootpass}"
  DB_NAME="${MYSQL_DATABASE:-varehusdb}"
  DB_USER="${MYSQL_USER:-webuser}"
  DB_PASS="${MYSQL_PASSWORD:-Passord123}"
  
  mysql --protocol=socket --socket="$SOCKET" -uroot <<SQL
ALTER USER 'root'@'localhost' IDENTIFIED BY '${ROOT_PASS}';
CREATE USER IF NOT EXISTS 'root'@'%' IDENTIFIED BY '${ROOT_PASS}';
GRANT ALL PRIVILEGES ON *.* TO 'root'@'%' WITH GRANT OPTION;
FLUSH PRIVILEGES;
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'%' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'%';
FLUSH PRIVILEGES;
SQL

  if [ -f /docker-entrypoint-initdb.d/varehusdb.sql ]; then
    echo "[init] importerer dump..."
    if sed -E -e "s/utf8mb4_0900_ai_ci/utf8mb4_unicode_ci/g" \
           -e "s/DEFAULT ENCRYPTION='N'//g" \
      /docker-entrypoint-initdb.d/varehusdb.sql \
    | mysql --protocol=socket --socket="$SOCKET" -uroot -p"${ROOT_PASS}" "${DB_NAME}"; then
      echo "[init] dump importert OK"
    else
      echo "[init] FEIL ved import av dump!" >&2
      exit 1
    fi
  fi
  
  touch "$INIT_FLAG"
  chown mysql:mysql "$INIT_FLAG"
  echo "[init] initialisering fullført"
  
  echo "[init] stopper MariaDB for normal start..."
  mysqladmin --protocol=socket --socket="$SOCKET" -uroot -p"${ROOT_PASS}" shutdown
  wait "$MYSQLD_PID"
else
  echo "[init] database allerede initialisert, starter normalt..."
  kill "$MYSQLD_PID" 2>/dev/null || true
  wait "$MYSQLD_PID" 2>/dev/null || true
fi

echo "[init] start mysqld normalt"
exec mysqld --datadir="$DATADIR" --user=mysql --bind-address=0.0.0.0
