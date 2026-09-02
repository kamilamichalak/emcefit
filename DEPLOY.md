# Wdrożenie DEMO na Render.com

Ten opis dotyczy **wyłącznie pokazowego** wdrożenia (jeden kontener, baza SQLite,
darmowy plan). Bez backupów, bez skalowania. Środowisko lokalne (Laravel Sail)
działa dalej bez zmian — te pliki są dodatkiem:

| Plik | Do czego |
|------|----------|
| `Dockerfile.production` | obraz produkcyjny (PHP 8.4 + zbudowany frontend, bez narzędzi dev) |
| `docker/render-start.sh` | start kontenera: tworzy plik SQLite, `migrate --force`, cache, `php artisan serve` |
| `render.yaml` | opcjonalny Blueprint Render (można też skonfigurować usługę ręcznie) |
| `.dockerignore` | trzyma lokalny `.env`, `vendor/`, `node_modules/`, dane testowe poza obrazem |

---

## 1. Wygeneruj `APP_KEY` lokalnie

W terminalu projektu (przez Sail):

```sh
./vendor/bin/sail artisan key:generate --show
```

albo bez Saila, jeśli masz lokalnie PHP:

```sh
php artisan key:generate --show
```

Skopiuj **całą** wypisaną wartość razem z prefiksem, np.:

```
base64:JZ9m1s0f2Xr7l1Qe0p8b6cVwtq3n5uZk8hG4dY2aA1M=
```

To będzie zmienna `APP_KEY` w panelu Render. (`--show` tylko wypisuje klucz,
nie zmienia Twojego lokalnego `.env`.)

---

## 2. Utwórz usługę na Render

**Wariant A — Blueprint (`render.yaml`):**
Render → *New +* → *Blueprint* → wskaż to repo. Render odczyta `render.yaml`
i poprosi o uzupełnienie zmiennych oznaczonych `sync: false` (`APP_KEY`, `APP_URL`).

**Wariant B — ręcznie:**
Render → *New +* → *Web Service* → repo → ustaw:

- **Runtime:** Docker
- **Dockerfile Path:** `./Dockerfile.production`
- **Plan:** Free
- **Health Check Path:** `/up`
- **Start Command:** *(zostaw puste — obraz sam uruchamia `render-start`)*

Następnie w zakładce **Environment** dodaj zmienne z tabeli poniżej.

---

## 3. Zmienne środowiskowe (panel Render → Environment)

**Wymagane:**

| Klucz | Wartość | Uwagi |
|-------|---------|-------|
| `APP_KEY` | `base64:...` | z kroku 1 |
| `APP_URL` | `https://<nazwa-usługi>.onrender.com` | dokładny publiczny adres usługi (https) |
| `APP_ENV` | `production` | |
| `APP_DEBUG` | `false` | na czas pierwszego uruchomienia możesz dać `true`, żeby widzieć szczegóły błędów |
| `DB_CONNECTION` | `sqlite` | |
| `DB_DATABASE` | `/app/database/database.sqlite` | plik w kontenerze; kasowany przy każdym wdrożeniu (patrz niżej) |

**Zalecane (unikają niespodzianek):**

| Klucz | Wartość | Uwagi |
|-------|---------|-------|
| `APP_LOCALE` | `pl` | interfejs po polsku |
| `LOG_CHANNEL` | `stderr` | logi widoczne w zakładce *Logs* na Render (domyślny plik `storage/logs` i tak jest ulotny) |
| `SESSION_DRIVER` | `database` | tabela `sessions` powstaje przy migracji |
| `SESSION_SECURE_COOKIE` | `true` | Render serwuje tylko https |
| `CACHE_STORE` | `database` | |
| `QUEUE_CONNECTION` | `sync` | brak workerów — aplikacja i tak nie kolejkuje zadań |

> `PHP_CLI_SERVER_WORKERS=4` jest już wpisane w obrazie (`Dockerfile.production`) —
> nie trzeba ustawiać.

Po zapisaniu zmiennych zrób *Manual Deploy* (albo `git push` — `autoDeploy` jest
włączony).

---

## 4. Ważne: ulotny dysk (co dzieje się przy każdym wdrożeniu)

Darmowy plan Render ma **efemeryczny dysk**. Każdy `git push` / redeploy /
restart uśpionej usługi tworzy **świeży kontener** i **kasuje plik SQLite**.

`docker/render-start.sh` obsługuje to automatycznie przy każdym starcie:

1. tworzy pusty `/app/database/database.sqlite`,
2. `php artisan migrate --force` — odtwarza schemat + tabele `sessions` / `cache` / `jobs`,
3. `php artisan db:seed --force` — **dane bazowe**: konto admina, role, typy karnetów, typy zajęć, trenerzy,
4. `php artisan config:cache && route:cache && view:cache`,
5. `php artisan serve` na porcie z `$PORT`.

**Dane testowe (86 klientek z zapisami) NIE są ładowane automatycznie** — wgrywasz je
ręcznie, kiedy chcesz (krok 5 poniżej).

---

## 5. Wgranie danych demo (ręcznie, po wdrożeniu)

W panelu Render otwórz **Shell** dla usługi i uruchom:

```sh
# 1. wzorzec tygodniowy + harmonogram na wrzesień 2026 (pod plik danych testowych)
php artisan db:seed --class=DemoScheduleSeeder --force

# 2. ~86 fikcyjnych klientek (@test.pl) z zapisami i płatnościami na wrzesień 2026
php artisan test-data:seed
```

Plik `storage/app/test-data/testowi_klienci.json` **nie jest w repo** (celowo).
Jeśli chcesz go użyć na Render, wgraj go najpierw przez Shell (np. `cat > ...`)
albo trzymaj w prywatnym miejscu i pobieraj `curl`-em. Bez tego pliku
`test-data:seed` po prostu nic nie utworzy.

Wyczyszczenie danych testowych (nie rusza konta admina ani słowników):

```sh
php artisan test-data:clear --force
```

> Uwaga: po kolejnym redeployu baza i tak wróci do stanu „tylko migracje +
> `DatabaseSeeder`", więc `DemoScheduleSeeder` + `test-data:seed` trzeba powtórzyć.

---

## 6. Logowanie do dema

Konto admina i słowniki tworzy `DatabaseSeeder` — uruchamiany **automatycznie**
przy każdym starcie kontenera (`render-start.sh`, krok 3). Nie musisz nic robić.

Dane logowania admina: `admin@emcefit.test` / hasło `password`
(z `DatabaseSeeder` — to demo, więc zwykłe testowe hasło).

Konta testowych klientek (po `test-data:seed`): e-mail z pliku
(`imie.nazwisko@test.pl`) / hasło `password`.

---

## 7. Skrót — po każdym wdrożeniu, jeśli chcesz pełne demo z klientkami

```sh
php artisan db:seed --class=DemoScheduleSeeder --force    # grafik wrzesień 2026
php artisan test-data:seed                                # klientki + zapisy (wymaga pliku JSON, patrz krok 5)
```

(`DatabaseSeeder` — admin + słowniki — już się wykonał automatycznie przy starcie.)
