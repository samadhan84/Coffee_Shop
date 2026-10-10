#!/usr/bin/env bash
# Starts MariaDB, loads the sample database on first run, then serves the app on port 8080.
set -e
cd "$(dirname "$0")/.."

sudo service mariadb start
for i in $(seq 1 30); do
    sudo mysqladmin ping --silent && break
    sleep 1
done

if ! sudo mysql -e "USE coffeeshop" 2>/dev/null; then
    echo "Importing database/coffeeshop.sql ..."
    sudo mysql < database/coffeeshop.sql
fi
sudo mysql -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
               CREATE USER IF NOT EXISTS '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASS}';
               GRANT ALL ON coffeeshop.* TO '${DB_USER}'@'localhost';
               GRANT ALL ON coffeeshop.* TO '${DB_USER}'@'127.0.0.1';"

pkill -f "php -S 0.0.0.0:8080" 2>/dev/null || true
nohup php -S 0.0.0.0:8080 -t . > /tmp/php-server.log 2>&1 &
echo "Coffee Shop is running on port 8080 (see the Ports tab)."
