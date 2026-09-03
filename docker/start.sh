#!/usr/bin/env sh
# Start command kontenera (Railway). Railway wstrzykuje PORT oraz zmienne serwisu
# MySQL — mapujemy je na standardowe DB_* Laravela, czekamy na bazę i puszczamy
# migracje (baza jest trwała, więc migracje tylko dokładają zmiany).
set -e

# --- 1. Port (Railway wymaga nasłuchu na $PORT) ---
: "${PORT:=8080}"

# --- 2. Baza: MySQL, mapowanie zmiennych Railway (MYSQL*) -> DB_* ---
: "${DB_CONNECTION:=mysql}"
export DB_CONNECTION

# Uzupełniamy DB_* tylko jeśli nie ustawiono ich wprost — pierwszeństwo ma jawny DB_*.
: "${DB_HOST:=${MYSQLHOST}}"
: "${DB_PORT:=${MYSQLPORT:-3306}}"
: "${DB_DATABASE:=${MYSQLDATABASE}}"
: "${DB_USERNAME:=${MYSQLUSER}}"
: "${DB_PASSWORD:=${MYSQLPASSWORD}}"
export DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD

if [ "$DB_CONNECTION" = "mysql" ] && [ -z "$DB_HOST" ]; then
    echo "BŁĄD: brak DB_HOST / MYSQLHOST — dodaj w Railway zmienne referencyjne serwisu MySQL (DEPLOY.md)."
    exit 1
fi

# --- 3. Poczekaj aż MySQL odpowie (pierwszy deploy potrafi wyprzedzić bazę) ---
if [ "$DB_CONNECTION" = "mysql" ]; then
    tries=0
    until php -r '
        try {
            new PDO(
                sprintf("mysql:host=%s;port=%s;dbname=%s", getenv("DB_HOST"), getenv("DB_PORT") ?: "3306", getenv("DB_DATABASE")),
                getenv("DB_USERNAME"), getenv("DB_PASSWORD"), [PDO::ATTR_TIMEOUT => 3]
            );
            exit(0);
        } catch (Throwable $e) { fwrite(STDERR, $e->getMessage().PHP_EOL); exit(1); }
    '; do
        tries=$((tries + 1))
        if [ "$tries" -ge 20 ]; then
            echo "BŁĄD: baza MySQL niedostępna po 20 próbach."
            exit 1
        fi
        echo "Czekam na bazę MySQL... ($tries)"
        sleep 3
    done
fi

# --- 4. Odkrycie pakietów + migracje (bez seedowania — baza jest trwała) ---
php artisan package:discover --ansi
php artisan migrate --force

# --- 5. Cache konfiguracji/tras/widoków — kod w obrazie jest niezmienny ---
php artisan config:cache
php artisan route:cache
php artisan view:cache

# --- 6. Serwer na porcie Railway ---
# --no-reload: obraz jest niezmienny; bez tej flagi PHP_CLI_SERVER_WORKERS jest ignorowane.
exec php artisan serve --host=0.0.0.0 --port="${PORT}" --no-reload
