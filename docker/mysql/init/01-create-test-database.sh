#!/bin/bash

set -euo pipefail

# A separate schema keeps `php artisan test` (LazilyRefreshDatabase) from
# wiping the development database.
TEST_DATABASE="${MYSQL_DATABASE}_test"

mysql --protocol=socket -uroot -p"${MYSQL_ROOT_PASSWORD}" <<SQL
CREATE DATABASE IF NOT EXISTS \`${TEST_DATABASE}\`
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON \`${TEST_DATABASE}\`.* TO '${MYSQL_USER}'@'%';
FLUSH PRIVILEGES;
SQL
