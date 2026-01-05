# Docker setup and commands for Painter (Laravel)

This guide shows how to run the app with Docker, explains each service, and lists the most common commands you’ll use day-to-day. It’s written for macOS with zsh but works similarly on Linux.

## What’s included

-   PHP-FPM 7.4 app container built from `Dockerfile` (container: `painter_app`)
-   Nginx serving on http://localhost (container: `painter_nginx`)
-   MySQL 5.7 with a persistent volume (container: `painter_mysql`)
-   phpMyAdmin on http://localhost:8081 (container: `painter_phpmyadmin`)
-   Auto-setup entrypoint that installs Composer deps, creates `.env`, generates app key, runs tenancy install (if missing), and runs migrations

All of this is defined in `docker-compose.yml` and `entrypoint.sh`.

## Prerequisites

-   Docker Desktop (macOS): https://www.docker.com/products/docker-desktop/
-   At least 4 GB RAM allocated to Docker (8 GB recommended if you run MySQL + app + build tasks)
-   Ports 80, 3306, and 8081 free on your machine

Note on Apple Silicon (M1/M2): `mysql:5.7` and `phpmyadmin` are pinned to `linux/amd64` for compatibility. Emulation works but may be slower.

## First-time setup (step-by-step)

1. Clone the repository

```bash
# choose a folder and clone
git clone <repo-url> painter
cd painter
```

2. (Optional) Prepare your environment file first

```bash
# if you want to edit before the first boot
cp .env.example .env
# then adjust any values you need, e.g. APP_NAME, APP_URL
```

Note: If `.env` is missing, the container’s entrypoint will copy `.env.example` to `.env` automatically and run `php artisan key:generate`.

3. Start the stack (build and run)

```bash
# build images and start in background
docker compose up -d --build
```

What happens on first boot:

-   Composer dependencies are installed (if `vendor/` is missing)
-   `.env` is created (if missing) and APP_KEY is generated
-   If `config/tenancy.php` is missing, `php artisan tenancy:install` is run
-   Database migrations run (`php artisan migrate --force`)

4. Verify containers are healthy and view logs

```bash
docker compose ps
# follow logs for the app and DB
docker compose logs -f app mysql
```

5. Open the app and DB UI in your browser

-   App via Nginx: http://localhost
-   phpMyAdmin: http://localhost:8081 (server: `mysql`, user: `painter_user`, password: `painter_pass`)

DB connection details (also set via compose env):

-   Host: `mysql`
-   Database: `painter_db`
-   Username: `painter_user`
-   Password: `painter_pass`

6. Confirm migrations ran (optional)

```bash
docker compose exec app php artisan migrate:status
```

7. (Optional) Create storage symlink, clear caches, etc.

```bash
docker compose exec app php artisan storage:link
docker compose exec app php artisan config:clear
docker compose exec app php artisan cache:clear
docker compose exec app php artisan route:clear
```

That’s it—your local environment should be up and running.

## Daily workflow commands (quick reference)

-   Start containers (foreground logs):

```bash
docker compose up
```

-   Start containers (detached):

```bash
docker compose up -d
```

-   Stop containers:

```bash
docker compose stop
```

-   Stop and remove containers (keeps DB volume):

```bash
docker compose down
```

-   Stop and remove containers AND the DB volume (data loss):

```bash
docker compose down -v
```

-   Rebuild the app image after changing the Dockerfile:

```bash
docker compose build app
# or force a clean rebuild
docker compose build --no-cache app
# then restart
docker compose up -d
```

-   See running containers:

```bash
docker compose ps
```

-   Tail logs (all services or specific ones):

```bash
docker compose logs -f
# or
docker compose logs -f app nginx mysql phpmyadmin
```

-   Open a shell in the app container:

```bash
docker compose exec app bash
```

-   Run Artisan commands:

```bash
docker compose exec app php artisan <command>
# examples:
docker compose exec app php artisan migrate
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed
docker compose exec app php artisan migrate:fresh --seed
docker compose exec app php artisan tinker
```

-   Run Composer:

```bash
docker compose exec app composer install
docker compose exec app composer dump-autoload -o
```

-   Run PHPUnit tests:

```bash
docker compose exec app ./vendor/bin/phpunit
```

-   MySQL CLI inside the DB container:

```bash
docker compose exec mysql mysql -u painter_user -ppainter_pass painter_db
```

-   Backup the DB to a file on your host:

```bash
# creates backup.sql in your current host folder
docker compose exec mysql mysqldump -u painter_user -ppainter_pass painter_db > backup.sql
```

-   Restore the DB from a backup file on your host:

```bash
# reads backup.sql from your current host folder
cat backup.sql | docker compose exec -T mysql mysql -u painter_user -ppainter_pass painter_db
```

-   Prune dangling images/containers (be careful):

```bash
docker system prune -f
```

## Service details

-   App (PHP-FPM 7.4)

    -   Built from the root `Dockerfile`
    -   Working dir: `/var/www`
    -   Runs the `entrypoint.sh` on startup
    -   Exposes port 9000 internally (handled behind Nginx)

-   Nginx

    -   Serves from `/var/www` using `nginx.conf`
    -   Binds to host port 80

-   MySQL 5.7

    -   Data persisted in the `mysql_data` Docker volume
    -   Binds to host port 3306
    -   Credentials from `docker-compose.yml` and `.env`

-   phpMyAdmin
    -   UI at http://localhost:8081

## How the entrypoint works

`entrypoint.sh` runs on every app container start:

1. Install Composer dependencies if `vendor/` is missing
2. Create `.env` from `.env.example` if `.env` is missing
3. Generate the Laravel APP_KEY
4. Set permissions on `storage/` and `bootstrap/cache/`
5. If `config/tenancy.php` is missing, run `php artisan tenancy:install`
6. Run `php artisan migrate --force`

If you don’t want automatic migrations or tenancy install in some environments, edit `entrypoint.sh` accordingly.

## Environment variables (DB)

These are provided to the app container by `docker-compose.yml`:

-   `DB_HOST=mysql`
-   `DB_DATABASE=painter_db`
-   `DB_USERNAME=painter_user`
-   `DB_PASSWORD=painter_pass`

Ensure your `.env` matches if you change them.

## Troubleshooting

-   Port already in use (bind error)

    -   Something else is using port 80, 3306, or 8081. Stop the process or change the port mappings in `docker-compose.yml`.

-   MySQL container slow to initialize

    -   On first boot, MySQL takes time to create the data directory. Watch with `docker compose logs -f mysql`. Re-run migrations after it’s ready if needed.

-   Permissions on storage/cache

    -   The entrypoint sets them, but if you see write errors, run:
        ```bash
        docker compose exec app chown -R www-data:www-data storage bootstrap/cache
        docker compose exec app chmod -R 775 storage bootstrap/cache
        ```

-   Composer memory or timeout issues

    -   Increase Docker Desktop memory/CPU, ensure stable network. You can also run composer locally and mount `vendor/`.

-   Apple Silicon performance
    -   `mysql:5.7` runs under emulation. Consider MySQL 8 ARM images for speed, but verify PHP 7.4 and app compatibility before switching.

## Common tasks and recipes

-   Clear and rebuild caches:

    ```bash
    docker compose exec app php artisan optimize:clear
    docker compose exec app php artisan config:cache
    docker compose exec app php artisan route:cache
    ```

-   Queue worker (one-off for testing):

    ```bash
    docker compose exec app php artisan queue:work --tries=1 --timeout=120
    ```

-   Run a single migration or seeder class:

    ```bash
    docker compose exec app php artisan migrate --path=database/migrations/2025_01_01_000000_example.php
    docker compose exec app php artisan db:seed --class=Database\\Seeders\\ExampleSeeder
    ```

-   Reset DB with fresh migrations and seeds (data loss):
    ```bash
    docker compose exec app php artisan migrate:fresh --seed
    ```

## Cleaning up

To stop everything and keep your DB:

```bash
docker compose down
```

To remove containers, networks, and the MySQL volume (DATA LOSS):

```bash
docker compose down -v
```

To remove unused images/containers globally (be careful):

```bash
docker system prune -f
```

## Appendix: container names and ports

-   `painter_app` (php-fpm): internal 9000, no host port
-   `painter_nginx`: host 80 -> container 80
-   `painter_mysql`: host 3306 -> container 3306 (volume: `mysql_data`)
-   `painter_phpmyadmin`: host 8081 -> container 80

---

If you run into issues not covered here, paste the output of:

```bash
docker compose ps && docker compose logs --no-color --tail=200
```

and I’ll help you debug.
