#!/bin/sh
set -e

# Copy .env if it does not exist
if [ ! -f .env ]; then
    echo "Creating .env from .env.example..."
    cp .env.example .env
fi

# Synchronize critical environment variables into .env
if [ -n "$DB_HOST" ]; then
    sed -i "s/^DB_HOST=.*/DB_HOST=$DB_HOST/" .env
fi
if [ -n "$DB_PORT" ]; then
    sed -i "s/^DB_PORT=.*/DB_PORT=$DB_PORT/" .env
fi
if [ -n "$DB_DATABASE" ]; then
    sed -i "s/^DB_DATABASE=.*/DB_DATABASE=$DB_DATABASE/" .env
fi
if [ -n "$DB_USERNAME" ]; then
    sed -i "s/^DB_USERNAME=.*/DB_USERNAME=$DB_USERNAME/" .env
fi
if [ -n "$DB_PASSWORD" ]; then
    sed -i "s/^DB_PASSWORD=.*/DB_PASSWORD=$DB_PASSWORD/" .env
fi
if [ -n "$DB_CONNECTION" ]; then
    sed -i "s/^DB_CONNECTION=.*/DB_CONNECTION=$DB_CONNECTION/" .env
fi
if [ -n "$MQTT_HOST" ]; then
    sed -i "s/^MQTT_HOST=.*/MQTT_HOST=$MQTT_HOST/" .env
fi
if [ -n "$MQTT_PORT" ]; then
    sed -i "s/^MQTT_PORT=.*/MQTT_PORT=$MQTT_PORT/" .env
fi
if [ -n "$APP_ENV" ]; then
    sed -i "s/^APP_ENV=.*/APP_ENV=$APP_ENV/" .env
fi
if [ -n "$APP_DEBUG" ]; then
    sed -i "s/^APP_DEBUG=.*/APP_DEBUG=$APP_DEBUG/" .env
fi
if [ -n "$APP_URL" ]; then
    sed -i "s|^APP_URL=.*|APP_URL=$APP_URL|" .env
fi

# Ensure APP_KEY exists
if ! grep -q "^APP_KEY=base64:" .env; then
    echo "Generating Application Key..."
    php artisan key:generate --force
fi

# Wait for MySQL database connection
echo "Waiting for MySQL database connection..."
until php -r "
    \$host = getenv('DB_HOST') ?: 'db';
    \$port = getenv('DB_PORT') ?: 3306;
    \$user = getenv('DB_USERNAME') ?: 'root';
    \$pass = getenv('DB_PASSWORD') ?: 'rootpassword';
    try {
        \$pdo = new PDO(\"mysql:host=\$host;port=\$port\", \$user, \$pass);
        exit(0);
    } catch (PDOException \$e) {
        exit(1);
    }
"; do
    echo "Database is unavailable - sleeping..."
    sleep 2
done

echo "Database connected successfully!"

# Run migrations
echo "Running database migrations..."
php artisan migrate --force

# Seeding: hanya jalankan jika belum ada data (mencegah duplikasi saat restart)
USER_COUNT=$(php artisan db:query --query="SELECT COUNT(*) as c FROM users" 2>/dev/null | grep -oP '\d+' | tail -1 || echo "0")
if [ "$USER_COUNT" = "0" ]; then
    echo "Seeding database (first time setup)..."
    php artisan db:seed --force
else
    echo "Database already seeded (${USER_COUNT} user ditemukan), skip seeding."
fi

echo "Creating storage symlink..."
php artisan storage:link --force || true

# Cache config & routes untuk production performance
echo "Caching config, routes & views..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Salin public assets ke shared volume agar nginx bisa melayani static files
echo "Copying public assets to shared nginx volume..."
cp -ru /var/www/html/public/. /shared/public/
echo "Public assets copied successfully."

echo "Starting process: $@"
exec "$@"

