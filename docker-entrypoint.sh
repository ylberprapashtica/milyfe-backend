#!/bin/sh
set -e

# Wait for database to be ready
echo "Waiting for database connection..."
until php -r "
  try {
    \$pdo = new PDO('pgsql:host=${DB_HOST};port=${DB_PORT}', '${DB_USERNAME}', '${DB_PASSWORD}');
    \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    exit(0);
  } catch (Exception \$e) {
    exit(1);
  }
" 2>/dev/null; do
  echo "Database not ready, waiting..."
  sleep 2
done
echo "Database connection established"

# Copy public files to shared volume if mounted
if [ -d "/app/public_volume" ]; then
  echo "Copying public files to shared volume..."
  cp -r /app/public/* /app/public_volume/ 2>/dev/null || true
fi

# Run Laravel optimizations
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

# Execute the main command
exec "$@"

