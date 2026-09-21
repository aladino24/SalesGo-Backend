#!/usr/bin/env bash
set -euo pipefail

if [[ ${EUID} -ne 0 ]]; then
  echo "Jalankan sebagai root: sudo bash scripts/server/install-production-stack.sh" >&2
  exit 1
fi

export DEBIAN_FRONTEND=noninteractive

apt-get update
apt-get install -y --no-install-recommends \
  ca-certificates \
  curl \
  git \
  unzip \
  nginx \
  php8.2-fpm \
  php8.2-cli \
  php8.2-mysql \
  php8.2-mbstring \
  php8.2-xml \
  php8.2-curl \
  php8.2-gd \
  php8.2-bcmath \
  php8.2-intl \
  php8.2-zip \
  composer \
  mysql-server \
  supervisor \
  certbot \
  python3-certbot-nginx

systemctl enable --now nginx
systemctl enable --now mysql
systemctl enable --now php8.2-fpm
systemctl enable --now supervisor

PHP_INI="/etc/php/8.2/fpm/php.ini"
if [[ -f "$PHP_INI" ]]; then
  sed -i 's/^;cgi.fix_pathinfo=.*/cgi.fix_pathinfo=0/' "$PHP_INI"
  sed -i 's/^memory_limit.*/memory_limit = 512M/' "$PHP_INI"
  sed -i 's/^upload_max_filesize.*/upload_max_filesize = 128M/' "$PHP_INI"
  sed -i 's/^post_max_size.*/post_max_size = 128M/' "$PHP_INI"
fi

mkdir -p /var/www/salesgo-api
mkdir -p /var/www/salesgo-api/storage /var/www/salesgo-api/bootstrap/cache
chown -R www-data:www-data /var/www/salesgo-api
chmod -R 775 /var/www/salesgo-api/storage /var/www/salesgo-api/bootstrap/cache

cat > /etc/nginx/conf.d/salesgo-api.conf <<'NGINX'
server {
    listen 80;
    listen [::]:80;
    server_name api.salesgo.local;
    root /var/www/salesgo-api/public;
    index index.php;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known) {
        deny all;
    }
}
NGINX

rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl reload nginx

cat > /etc/supervisor/conf.d/salesgo-api-worker.conf <<'SUPERVISOR'
[program:salesgo-api-worker]
process_name=%(program_name)s_%(process_num)02d
command=/usr/bin/php /var/www/salesgo-api/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
directory=/var/www/salesgo-api
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/salesgo-api-worker.log
stopwaitsecs=60
SUPERVISOR

supervisorctl reread
supervisorctl update

cat > /etc/mysql/mysql.conf.d/mysqld.cnf <<'MYSQL'
[mysqld]
user = mysql
datadir = /var/lib/mysql
socket = /var/run/mysqld/mysqld.sock
bind-address = 127.0.0.1
mysqlx-bind-address = 127.0.0.1
symbolic-links=0
default_authentication_plugin = mysql_native_password
MYSQL

systemctl restart mysql

cat <<'EOF'
========================================
Production stack installed.
Next steps:
1. Create MySQL database and user.
2. Configure .env on /var/www/salesgo-api.
3. Run: composer install --no-dev --optimize-autoloader
4. Run: php artisan key:generate --force
5. Run: php artisan migrate --force
6. Run: certbot --nginx -d api.salesgo.local
========================================
EOF
