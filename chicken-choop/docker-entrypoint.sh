#!/bin/sh
set -e

# Copy .env if it does not exist
if [ ! -f .env ]; then
    echo "Creating .env from .env.example..."
    cp .env.example .env
fi

# Helper function untuk sync environment variable ke .env
set_env_var() {
    key="$1"
    value="$2"
    if [ -n "$value" ]; then
        if grep -q "^#\? \?${key}=" .env; then
            sed -i "s|^#\? \?${key}=.*|${key}=${value}|" .env
        else
            echo "${key}=${value}" >> .env
        fi
    fi
}

# Synchronize critical environment variables into .env
set_env_var "APP_NAME" "$APP_NAME"
set_env_var "APP_ENV" "$APP_ENV"
set_env_var "APP_DEBUG" "$APP_DEBUG"
set_env_var "APP_URL" "$APP_URL"
set_env_var "DB_CONNECTION" "$DB_CONNECTION"
set_env_var "DB_HOST" "$DB_HOST"
set_env_var "DB_PORT" "$DB_PORT"
set_env_var "DB_DATABASE" "$DB_DATABASE"
set_env_var "DB_USERNAME" "$DB_USERNAME"
set_env_var "DB_PASSWORD" "$DB_PASSWORD"
set_env_var "MQTT_HOST" "$MQTT_HOST"
set_env_var "MQTT_PORT" "$MQTT_PORT"
set_env_var "MQTT_USERNAME" "$MQTT_USERNAME"
set_env_var "MQTT_PASSWORD" "$MQTT_PASSWORD"
set_env_var "SESSION_DRIVER" "$SESSION_DRIVER"
set_env_var "CACHE_STORE" "$CACHE_STORE"
set_env_var "QUEUE_CONNECTION" "$QUEUE_CONNECTION"

# Ensure APP_KEY exists
if ! grep -q "^APP_KEY=base64:" .env; then
    echo "Generating Application Key..."
    php artisan key:generate --force
fi

# Wait for MySQL database connection
echo "Waiting for MySQL database connection..."
MAX_TRIES=30
COUNT=0
until php -r "
    \$host = getenv('DB_HOST') ?: 'db';
    \$port = getenv('DB_PORT') ?: 3306;
    \$user = getenv('DB_USERNAME') ?: 'root';
    \$pass = getenv('DB_PASSWORD') ?: 'rootpassword';
    \$db   = getenv('DB_DATABASE') ?: 'db_chicken_choop';
    try {
        \$pdo = new PDO(\"mysql:host=\$host;port=\$port;dbname=\$db\", \$user, \$pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 3
        ]);
        echo \"Connected successfully to database '\$db' as user '\$user'!\n\";
        exit(0);
    } catch (PDOException \$e) {
        echo \"Connection attempt failed: \" . \$e->getMessage() . \"\n\";
        exit(1);
    }
"; do
    COUNT=$((COUNT+1))
    if [ $COUNT -ge $MAX_TRIES ]; then
        echo "WARNING: Reached maximum connection attempts ($MAX_TRIES). Proceeding anyway..."
        break
    fi
    echo "Database is unavailable ($COUNT/$MAX_TRIES) - sleeping 2s..."
    sleep 2
done

echo "Database connected successfully!"

# Run migrations
echo "Running database migrations..."
php artisan migrate --force

# Seeding: hanya jalankan jika belum ada data user (mencegah duplikasi saat restart)
USER_COUNT=$(php artisan tinker --execute="echo \App\Models\User::count();" 2>/dev/null | tr -dc '0-9' || echo "0")
if [ "$USER_COUNT" = "0" ] || [ -z "$USER_COUNT" ]; then
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
mkdir -p /shared/public
cp -rf /var/www/html/public/. /shared/public/
chmod -R 755 /shared/public
echo "Public assets copied successfully."

echo "Starting process: $@"
exec "$@"

