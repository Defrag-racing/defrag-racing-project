#!/bin/sh

# Check if the run from the top-level directory
if [ ! -f "./artisan" ]; then
    echo "Error: This script should be run from the top-level project directory"
    exit 1
fi

# Check if Docker is running
if ! docker info >/dev/null 2>&1; then
    echo "Error: Docker does not seem to be running, start Docker first and re-run the script"
    exit 1
fi

PROJECT_ROOT_DIR=$(basename "$(pwd)")

# Stop current Sail containers and remove defrag related docker files
./vendor/bin/sail stop
docker rm "${PROJECT_ROOT_DIR}-laravel.test-1"
docker rm "${PROJECT_ROOT_DIR}-mysql-1"
docker rm "${PROJECT_ROOT_DIR}-typesense-1"
docker volume rm "${PROJECT_ROOT_DIR}_sail-mysql"
docker volume rm "${PROJECT_ROOT_DIR}_sail-typesense"

# Copy nessessary files to start Sail
cp -v ./local_devel/.env.local_devel .env
cp -v ./local_devel/docker-compose.yml.local_devel docker-compose.yml

# Build and start Sail
SAIL_NO_CACHE=false
COMPOSER_OUTPUT=$(composer update laravel/sail --no-autoloader --no-scripts 2>&1)
if echo "$COMPOSER_OUTPUT" | grep -q "Upgrading laravel/sail"; then
    SAIL_NO_CACHE=true
fi

./local_devel/apply_patch.sh

composer dump-autoload
composer run-script post-update-cmd

if SAIL_NO_CACHE; then
    ./vendor/bin/sail build --no-cache
else
    ./vendor/bin/sail build
fi
./vendor/bin/sail up -d

# Wait for Sail to start
sleep 5

# Generate application key
./vendor/bin/sail artisan key:generate

# Wait for MySQL to be "healthy"
# Check every 2 seconds for 5 minutes
end=$((SECONDS+300))
success=false
while [ $SECONDS -lt $end ]; do
    if ./vendor/bin/sail ps | grep -q -P 'mysql.*healthy'; then
        success=true
        break
    fi
    echo "Waiting for MySQL to load..."
    sleep 2
done

if [ "$success" = false ]; then
    echo "Error: MySQL did not load properly! Exit!"
    exit 1
fi

# Run rest of the Sail commands to finish setup
./vendor/bin/sail artisan migrate
./vendor/bin/sail npm install
./vendor/bin/sail npm run build
./vendor/bin/sail artisan storage:link --force
./vendor/bin/sail artisan filament:assets
./vendor/bin/sail artisan config:cache
./vendor/bin/sail artisan route:cache
./vendor/bin/sail artisan view:cache
./vendor/bin/sail artisan icons:cache
./vendor/bin/sail artisan octane:reload
./vendor/bin/sail artisan queue:restart
