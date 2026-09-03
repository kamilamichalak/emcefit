# Wdrożenie na Railway.app (trwałe demo)

Wdrożenie na **Railway** jako dwa serwisy w jednym projekcie:

- **aplikacja** — kontener z `Dockerfile.production` (PHP 8.4 + zbudowany frontend, `php artisan serve` na `$PORT`),
- **MySQL** — osobny serwis Railway z własnym wolumenem (dane **przeżywają** deploye).

Środowisko lokalne (Laravel Sail) działa dalej bez zmian — te pliki są dodatkiem:

| Plik | Do czego |
|------|----------|
| `Dockerfile.production` | obraz aplikacji (multi‑stage: composer `--no-dev` → `npm run build` → `php:8.4-cli` z `pdo_mysql`) |
| `docker/start.sh` | start kontenera: mapuje `MYSQL*` → `DB_*`, czeka na bazę, `migrate --force`, cache, `serve` na `$PORT` |
| `railway.toml` | wskazuje Railwayowi `Dockerfile.production` + health check `/up` |
| `.dockerignore` | trzyma `.env`, `vendor/`, `node_modules/`, dane testowe poza obrazem |

---

## 1. Wygeneruj `APP_KEY` lokalnie

```sh
./vendor/bin/sail artisan key:generate --show
```

Skopiuj **całą** wartość z prefiksem `base64:` — to będzie zmienna `APP_KEY` w Railway.
(`--show` tylko wypisuje klucz, nie zmienia lokalnego `.env`.)

---

## 2. Utwórz projekt i serwisy na Railway

1. **railway.app** → *New Project* → *Deploy from GitHub repo* → wybierz repo z tą aplikacją.
   Railway wykryje `railway.toml` i zbuduje z `Dockerfile.production`.
2. W tym samym projekcie: *New* → *Database* → **Add MySQL**.
   Nazwij serwis np. `MySQL` (domyślna nazwa) — użyjesz jej w referencjach zmiennych.
3. Serwis aplikacji → *Settings* → *Networking* → **Generate Domain**
   (dostaniesz adres w stylu `emcefit-production.up.railway.app`).

---

## 3. Zmienne środowiskowe — serwis **aplikacji** (Variables)

Railway sam wstrzykuje `PORT` — **nie ustawiaj go ręcznie**.

**Wymagane:**

| Klucz | Wartość |
|-------|---------|
| `APP_KEY` | `base64:...` (z kroku 1) |
| `APP_URL` | `https://<twój-adres>.up.railway.app` (adres z kroku 2.3) |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` *(na pierwsze uruchomienie możesz dać `true`, żeby widzieć szczegóły błędów)* |
| `DB_CONNECTION` | `mysql` |
| `MYSQLHOST` | `${{ MySQL.MYSQLHOST }}` |
| `MYSQLPORT` | `${{ MySQL.MYSQLPORT }}` |
| `MYSQLDATABASE` | `${{ MySQL.MYSQLDATABASE }}` |
| `MYSQLUSER` | `${{ MySQL.MYSQLUSER }}` |
| `MYSQLPASSWORD` | `${{ MySQL.MYSQLPASSWORD }}` |

> `${{ MySQL.* }}` to **referencje** do serwisu MySQL — jeśli nazwałaś serwis inaczej niż
> `MySQL`, podmień tę część (np. `${{ emcefit-db.MYSQLHOST }}`). Railway podpowiada te
> referencje pod przyciskiem *Add Reference* / *Variable Reference* w edytorze zmiennych.
>
> `docker/start.sh` mapuje `MYSQLHOST/PORT/DATABASE/USER/PASSWORD` na `DB_HOST/DB_PORT/`
> `DB_DATABASE/DB_USERNAME/DB_PASSWORD`, których używa Laravel. Jeśli wolisz, możesz zamiast
> tego ustawić wprost `DB_HOST=${{ MySQL.MYSQLHOST }}` itd. — jawne `DB_*` mają pierwszeństwo.

**Zalecane (unikają niespodzianek):**

| Klucz | Wartość | Uwagi |
|-------|---------|-------|
| `APP_LOCALE` | `pl` | interfejs po polsku |
| `LOG_CHANNEL` | `stderr` | logi w zakładce *Deployments → Logs* na Railway |
| `SESSION_DRIVER` | `database` | tabela `sessions` powstaje przy migracji |
| `SESSION_SECURE_COOKIE` | `true` | Railway serwuje tylko https |
| `CACHE_STORE` | `database` | |
| `QUEUE_CONNECTION` | `sync` | brak workerów — aplikacja i tak nie kolejkuje zadań |

> `PHP_CLI_SERVER_WORKERS=4` jest już wpisane w obrazie — nie trzeba ustawiać.

Po zapisaniu zmiennych Railway sam zrobi redeploy. W logach zobaczysz kolejno:
`Czekam na bazę MySQL...` → `Discovering packages` → migracje → `Configuration/Routes/Blade cached`
→ `Server running on [http://0.0.0.0:<PORT>]` → health check `/up` = OK.

---

## 4. Po pierwszym wdrożeniu — dane bazowe (raz)

`start.sh` uruchamia **tylko migracje**, nie seeduje (baza jest trwała, nie chcemy
nadpisywać ewentualnych ręcznych zmian, np. cen karnetów).

Wejdź w serwis aplikacji → zakładka konsoli (Railway *Shell* / *Command*), albo lokalnie
`railway run` z CLI, i uruchom **raz**:

```sh
php artisan db:seed --force
```

Tworzy: konto admina, role, 14 typów karnetów, typy zajęć, 1 trenera.
Przy kolejnych deployach **nie powtarzaj** tego — migracje same dokładają nowe zmiany
w schemacie, dane zostają.

---

## 5. (Opcjonalnie) pełne demo z ~86 klientkami

```sh
# wzorzec tygodniowy + harmonogram na wrzesień 2026 (pod plik danych testowych)
php artisan db:seed --class=DemoScheduleSeeder --force

# ~86 fikcyjnych klientek (@test.pl) z zapisami i płatnościami na wrzesień 2026
php artisan test-data:seed
```

Plik `storage/app/test-data/testowi_klienci.json` **nie jest w repo** (celowo). Żeby użyć
go na Railway, wgraj go najpierw przez konsolę serwisu (np. `cat > storage/app/test-data/testowi_klienci.json`
i wklej zawartość), albo pobierz `curl`-em z prywatnego miejsca. Bez tego pliku
`test-data:seed` nic nie utworzy.

Wyczyszczenie danych testowych (nie rusza admina ani słowników):

```sh
php artisan test-data:clear --force
```

Na Railway te dane **zostają** między deployami (w przeciwieństwie do wcześniejszego
wariantu Render z SQLite).

---

## 6. Logowanie do dema

Po `php artisan db:seed --force` (krok 4):

- **admin:** `admin@emcefit.test` / `password`
- **trener:** `trener@emcefit.test` / `password`
- **klientki** (po `test-data:seed`): e‑mail z pliku (`imie.nazwisko@test.pl`) / `password`

---

## 7. Zmiana adresu

Jeśli wygenerowany adres Railway różni się od tego, co wpisałaś w `APP_URL`
(albo dodasz własną domenę) — popraw `APP_URL` w Variables i zapisz. Ma znaczenie dla
podpisanych linków (aktywacja konta / reset hasła).
