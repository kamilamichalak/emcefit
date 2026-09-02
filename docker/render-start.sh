#!/usr/bin/env sh
# Start command kontenera na Render (DEMO). Darmowy plan = ulotny dysk: przy każdym
# wdrożeniu/starcie kontener jest świeży, więc plik SQLite tworzymy od zera i
# odtwarzamy schemat. Danych testowych NIE ładujemy automatycznie — od tego jest
# ręczne `php artisan test-data:seed` (patrz DEPLOY.md).
set -e

: "${DB_CONNECTION:=sqlite}"
: "${DB_DATABASE:=/app/database/database.sqlite}"
export DB_CONNECTION DB_DATABASE

if [ "$DB_CONNECTION" = "sqlite" ]; then
    mkdir -p "$(dirname "$DB_DATABASE")"
    [ -f "$DB_DATABASE" ] || : > "$DB_DATABASE"
fi

# Odkrycie pakietów (build był z --no-scripts), potem schemat na świeżej bazie.
php artisan package:discover --ansi
php artisan migrate --force

# Dane bazowe: konto admina + słowniki (typy karnetów/zajęć, role, trenerzy).
# Baza jest przy każdym starcie pusta, więc DatabaseSeeder jest tu bezpieczny.
# UWAGA: to NIE ładuje danych testowych (86 klientek) — od tego jest ręczne
# `php artisan test-data:seed` (patrz DEPLOY.md).
php artisan db:seed --force

# Cache konfiguracji/tras/widoków — kod w obrazie jest niezmienny.
php artisan config:cache
php artisan route:cache
php artisan view:cache

# --no-reload: obraz jest niezmienny; bez tej flagi PHP_CLI_SERVER_WORKERS jest ignorowane.
exec php artisan serve --host=0.0.0.0 --port="${PORT:-8080}" --no-reload
