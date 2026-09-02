# Specyfikacja: System zarządzania klubem fitness

Wersja: 0.1 (draft roboczy) | Data: 2026-09-01

---

## 1. Cel projektu

Aplikacja webowa do zarządzania klubem fitness: baza klientów, karnety i płatności,
rezerwacje zajęć grupowych, w przyszłości plany treningowe i obsługa wielu trenerów/lokalizacji.

Zasady biznesowe (np. zasady rezygnacji z zajęć, ważność karnetów, kary za spóźnienia)
mają zostać uzupełnione na podstawie regulaminu klubu — **do zrobienia w kroku 2**.

---

## 2. Role użytkowników

| Rola | Uprawnienia |
|---|---|
| **Admin** | pełny dostęp: klienci, płatności, grafik, ustawienia klubu |
| **Trener** | podgląd/edycja swojego grafiku, lista zapisanych na zajęcia, (w Fazie 3: plany treningowe) |
| **Klient** | swój profil, historia płatności/karnet, rezerwacja i odwołanie zajęć |

> Uwaga projektowa: mimo że na start jest 1 trener, tabela `trainers` i uprawnienia
> budujemy od razu jako relację 1-do-wielu (klub ↔ trenerzy), żeby dodanie kolejnego
> trenera było zmianą danych, a nie zmianą kodu.

---

## 3. Zakres funkcjonalny — fazy

### Faza 1 (MVP) — klienci i płatności
- CRUD klientów (dane osobowe, kontakt, status aktywny/nieaktywny)
- Rodzaje karnetów zgodne z cennikiem klubu, m.in.:
  - **Abonament zamknięty (z rezerwacją miejsc)** — zależny od częstotliwości zajęć w tygodniu (1x/2x/3x/4x) i długości okresu (miesięczny lub krótkoterminowy 2-3 tyg., jednorazowo, bez ciągłości)
  - **Abonament otwarty** — pakiet X wejść, ważny 5 tygodni od daty pierwszego wejścia, bez gwarancji stałego miejsca
  - **Abonament bez limitu** — miesięczny, nielimitowana liczba wejść na zasadzie dostępnych miejsc
  - **Wejście jednorazowe** oraz **dodatkowy trening przy aktywnym abonamencie** (dopłata)
- Każdy typ karnetu konfigurowalny przez admina: cena, liczba wejść (jeśli dotyczy), tryb liczenia ważności (miesiąc kalendarzowy / X tygodni od pierwszego wejścia), tryb rezerwacji (stały/otwarty)
- Rejestrowanie płatności — **ręcznie, na podstawie przelewów bankowych** (regulamin nie przewiduje płatności online → integracja ze Stripe niepotrzebna w MVP, mniej pracy na start)
- Dashboard admina: kończące się karnety, niezaksięgowane/zaległe wpłaty, licznik aktywnych abonamentów otwartych (regulamin: limit 20/mies.)

### Faza 2 — rezerwacje zajęć
- **Grupy zajęciowe** (stałe, powtarzalne terminy tygodniowe, np. "Pon/Śr 18:00") — klient z abonamentem zamkniętym zapisuje się do grupy na cały okres ważności karnetu
- Klient z abonamentem otwartym/bez limitu zapisuje się na pojedyncze wystąpienia zajęć, wyłącznie na wolne miejsca, bez gwarancji stałego miejsca
- **Kluczowa reguła:** rezerwacja jest potwierdzona dopiero po zaksięgowaniu wpłaty, a kolejność na liście/liście oczekujących wynika z **daty zaksięgowania płatności, nie daty zgłoszenia**
- Odwołanie pojedynczych zajęć (abonament zamknięty) → prawo do **odrobienia** w tym samym miesiącu kalendarzowym, na wolnych miejscach w innych grupach, przy zgłoszeniu min. 1h przed zajęciami
- **Cykl potwierdzania kontynuacji** — pod koniec miesiąca klient musi potwierdzić chęć kontynuacji na kolejny miesiąc; brak potwierdzenia/wpłaty do 28. dnia miesiąca = automatyczne zwolnienie miejsca
- Niewykorzystane wejścia nie przechodzą na kolejny miesiąc i nie podlegają zwrotowi

### Faza 3 (backlog, nie teraz)
- Panel trenera z planami treningowymi dla klientów
- Wielolokalizacyjność / multi-tenant
- Aplikacja mobilna / PWA
- Płatności cykliczne online (np. Stripe)
- Powiadomienia e-mail/SMS
- Edycja cennika/typów karnetów przez admina w panelu (w Fazie 1 to dane startowe/seed;
  edycja samej ceny wdrożona wcześniej — sekcja 14; pełny CRUD pozostałych atrybutów
  typu karnetu wciąż w backlogu)

---

## 4. Model danych (szkic)

```
clubs
 └─ id, nazwa, dane_kontaktowe

users              -- wspólna tabela logowania (admin/trener/klient)
 └─ id, imie, nazwisko, email, hash_hasla, rola, club_id

clients            -- rozszerzenie usera o dane specyficzne dla klienta
 └─ id, user_id, telefon, data_urodzenia,
    status (JEDEN status klienta: aktywny/nieaktywny; nowy klient = nieaktywny;
      ustawiany przez admina ręcznie ALBO automatycznie, gdy klient ukończy aktywację
      z linku — patrz sekcja 12), data_dolaczenia,
    regulamin_zaakceptowany_at, oswiadczenie_zdrowotne_at,
    zaproszenie_wykorzystane_at (nullable — czy klient skonfigurował LOGOWANIE przez link:
      puste = nie ma hasła, nie zaloguje się; wypełnione = może. To NIE jest osobny status
      w UI, tylko funkcja — aktywny klient nie musi mieć skonfigurowanego logowania)

trainers           -- rozszerzenie usera o dane trenera
 └─ id, user_id, specjalizacja

membership_types   -- rodzaje karnetów wg cennika klubu, konfigurowane przez admina
 └─ id, nazwa, tryb (zamkniety / otwarty / bez_limitu / jednorazowe),
    sesje_w_tygodniu (nullable), liczba_wejsc (nullable),
    okres_waznosci_typ (miesiac_kalendarzowy / tygodnie_od_pierwszego_wejscia),
    okres_waznosci_wartosc, cena

memberships        -- karnet przypisany klientowi
 └─ id, client_id, membership_type_id,
    cena_ustalona (decimal — migawka ceny z membership_types.cena W MOMENCIE założenia
    karnetu; nie zmienia się, nawet jeśli cennik zostanie później zedytowany, Prompt 11),
    data_pierwszego_wejscia, data_od, data_do, wejscia_pozostale,
    kontynuacja_potwierdzona (bool, resetowane co miesiąc)

membership_class_groups  -- NOWOŚĆ: wybrane zajęcia w ramach karnetu zamkniętego (relacja wiele-do-wielu,
                          -- bo klient może wybrać kilka różnych zajęć/tydzień w ramach jednego karnetu)
 └─ id, membership_id, class_group_id

payments
 └─ id, client_id, membership_id, kwota, data_zgloszenia,
    data_zaksiegowania (nullable), status (oczekuje/zaksiegowana/anulowana), tytul_przelewu

class_types        -- np. Body Pump, TBC, TBC Max, HIIT, Fit Dance, Funkcjonal Choreo Step, Mix Treningowy
 └─ id, nazwa, opis, wymaga_sprzetu (np. "sztangi", nullable, informacyjnie),
    kolor (hex, np. #E91E63 — do wizualnego oznaczenia na grafiku),
    domyslny_limit_miejsc (liczba, domyślnie 20)

class_groups       -- wzorzec tygodniowy, wersjonowany per miesiąc
 └─ id, class_type_id, trainer_id, dzien_tygodnia (pon-pt), godzina,
    czas_trwania_min (domyślnie 55), limit_miejsc,
    obowiazuje_od (rok-miesiąc), obowiazuje_do (nullable, rok-miesiąc)

class_schedule     -- konkretne wystąpienie zajęć w danym dniu (generowane z class_groups na dany miesiąc)
 └─ id, class_group_id, data, godzina (może odbiegać od wzorca dla pojedynczego wystąpienia),
    status (planowane/odwolane), powod_odwolania (nullable)

zapisy_miesieczne  -- NOWOŚĆ: czy admin otworzył zapisy klientów na dany miesiąc
 └─ id, rok, miesiac, zapisy_otwarte (bool, domyślnie false), otwarte_od (nullable, timestamp)
    -- wygenerowanie harmonogramu (class_schedule) NIE otwiera zapisów automatycznie;
    -- to zawsze świadoma, osobna decyzja admina (patrz sekcja 13)

reservations
 └─ id, client_id, class_schedule_id, membership_id,
    status (oczekuje_platnosci/potwierdzona/waitlist/zwolnione/odrobiona),
    data_zgloszenia, data_potwierdzenia (= data zaksięgowania powiązanej płatności)

makeup_credits     -- odrobienia po odwołaniu zajęć (przez klienta LUB z góry przez admina)
 └─ id, client_id, source_reservation_id, wygasa_koniec_miesiaca (bool), wykorzystany (bool)
```

To jest szkic pod Fazę 1+2 — nie modelujemy jeszcze planów treningowych (Faza 3).

**Uwaga:** kolejność w `reservations`/waitliście ustalana jest po `data_potwierdzenia` (czyli po zaksięgowaniu wpłaty), a nie po `data_zgloszenia` — to bezpośrednio z regulaminu (pkt 36: "o miejscu na liście decyduje kolejność wpłat, nie zgłoszeń").

**Logika wzorca miesięcznego (`class_groups`):** admin ustawia wzorzec tygodniowy raz — obowiązuje przez cały miesiąc kalendarzowy i, dopóki nie zostanie świadomie zamknięty, **dziedziczony jest bezterminowo przez kolejne miesiące** (patrz niżej). Z zatwierdzonego wzorca generowane są konkretne wystąpienia w `class_schedule` dla wszystkich dni danego miesiąca. Admin może odwołać pojedyncze wystąpienie w `class_schedule` (np. z powodu święta) bez ruszania wzorca — to automatycznie tworzy `makeup_credits` dla klientów zapisanych na stałe w tej grupie.

**Dziedziczenie wzorca w widoku (read-only).** Wiersz `class_groups` z `obowiazuje_do = null` obowiązuje bezterminowo — czyli wzorzec ułożony na wrzesień "widać" też w październiku, listopadzie itd., dopóki nie zostanie zamknięty. Żeby nie wprowadzać w błąd (i nie dopuścić do przypadkowej edycji wielu miesięcy naraz), panel rozróżnia:
- **miesiąc własny** — ma co najmniej jeden wiersz `class_groups` z `obowiazuje_od` w tym miesiącu → pełna edycja (dodawanie / edycja / usuwanie zajęć)
- **miesiąc dziedziczony** — pokazuje wzorzec, ale żaden wiersz nie jest w nim zakotwiczony → widok tylko do odczytu, wyszarzony, z informacją "Wzorzec dziedziczony z: \<miesiąc\>" i przyciskiem "Skopiuj wzorzec na \<ten miesiąc\>"

Kopiowanie (`class_groups.copy`) przyjmuje miesiąc docelowy: zamyka wiersze dziedziczone (`obowiazuje_do` = miesiąc poprzedzający docelowy) i tworzy ich kopie z `obowiazuje_od` = miesiąc docelowy, `obowiazuje_do = null`. Działa tak samo dla "skopiuj bieżący na kolejny" i "uczyń ten (dziedziczony) miesiąc edytowalnym". Jeśli miesiąc docelowy ma już własny wzorzec — ostrzeżenie i nadpisanie tylko po wyraźnym potwierdzeniu.

---

## 5. Kluczowe przepływy (user flows)

1. **Admin dodaje klienta** → przypisuje karnet → odhacza płatność
2. **Admin/trener tworzy grafik zajęć** na dany tydzień
3. **Klient loguje się** → widzi grafik → rezerwuje miejsce (system sprawdza ważność karnetu/limit wejść)
4. **Klient odwołuje rezerwację** → zwolnione miejsce trafia do pierwszej osoby z waitlisty
5. **Admin widzi dashboard**: kończące się karnety, zaległości płatnicze, frekwencja na zajęciach

---

## 6. Stack technologiczny

- **Backend:** Laravel (PHP) — routing, logika biznesowa, autoryzacja
- **Frontend:** Vue 3 + Inertia.js (SPA-like, bez osobnego REST API)
- **Baza danych:** MySQL
- **Autoryzacja/role:** Laravel Breeze + `spatie/laravel-permission`
- **Płatności (Faza 2+):** Laravel Cashier (Stripe) — na start płatności ręczne/gotówkowe
- **Styling:** Tailwind CSS (domyślnie idzie z Breeze, dobrze się integruje z Vue)

## 7. Architektura pod dalszy rozwój

Żeby dało się to "mądrze rozwijać":
- **Podział na moduły/domeny** w kodzie (np. `app/Domain/Clients`, `app/Domain/Memberships`,
  `app/Domain/Reservations`) zamiast wszystkiego w jednym folderze kontrolerów — łatwiej o
  dodawanie nowych funkcji bez rozjeżdżania się kodu.
- **Migracje bazy danych od pierwszego dnia** (Laravel to wymusza) — pełna historia zmian schematu.
- **Seedery i fabryki testowe** — do szybkiego generowania danych testowych przy rozwoju z AI.
- **Testy** przynajmniej na kluczowej logice (np. "czy klient bez ważnego karnetu może się zapisać na zajęcia" — to musi być pokryte testem, bo to reguła biznesowa łatwa do przypadkowego zepsucia).
- **Konfigurowalność przez admina** zamiast hardkodowania (np. rodzaje karnetów, typy zajęć) — mniej zmian w kodzie przy zmianach biznesowych.

---

## 8. Zasady biznesowe z regulaminu — już uwzględnione w modelu

- Odwołanie zajęć (abonament zamknięty): zgłoszenie min. 1h przed, prawo do odrobienia w tym samym miesiącu, na wolnych miejscach w innych grupach
- Niewykorzystane wejścia **nie** przechodzą na kolejny miesiąc i **nie** podlegają zwrotowi
- Zamrażanie karnetu: **regulamin tego nie przewiduje** — nie implementujemy w MVP
- Okres próbny / pierwsze zajęcia gratis: **regulamin tego nie przewiduje**
- Płatności wyłącznie przelewem bankowym, rejestrowane ręcznie przez admina — brak integracji online w MVP
- Kolejność na liście/waitliście wg daty zaksięgowania wpłaty, nie zgłoszenia

## 8a. Decyzje podjęte

- Limit 20 abonamentów otwartych/mies. → **nieegzekwowany przez system**, tylko licznik informacyjny na dashboardzie admina (już w zakresie Fazy 1)
- Potwierdzanie kontynuacji → **klient sam potwierdza** przyciskiem w swoim panelu (wpływa na `memberships.kontynuacja_potwierdzona`)
- Cennik karnetów w Fazie 1 → **dane startowe (seed)** wpisane raz na podstawie obecnego cennika; edycja przez admina w panelu to zadanie na Fazę 3 (dopisane do backlogu)
- **Wzorzec grafiku: dziedziczenie zamiast automatycznej kopii** (dodane 2026-09-01) — wzorzec `class_groups` z otwartym `obowiazuje_do` obowiązuje do odwołania; przyszłe miesiące dziedziczą go w trybie read-only (wyszarzone), a nie dostają automatycznej kopii. Osobny wzorzec dla danego miesiąca powstaje dopiero po świadomym kliknięciu "Skopiuj wzorzec". Uzasadnienie: brak mylącego wrażenia "już skopiowane" i brak ryzyka, że edycja jednego miesiąca po cichu zmienia pozostałe.

**Założenie robocze** (do potwierdzenia, ale przyjmuję jako rozsądny domyślny wybór): przy pierwszej rejestracji/zakupie karnetu w systemie klient zaznacza checkbox "zapoznałem się z regulaminem" i "oświadczam brak przeciwwskazań zdrowotnych" — to prosty do wdrożenia ślad prawny (pola `regulamin_zaakceptowany_at`, `oswiadczenie_zdrowotne_at` już są w modelu `clients`). Daj znać, jeśli wolisz to zostawić poza systemem.

- **Bezpieczeństwo kont personelu** (dodane 2026-09-01) — Laravel Breeze domyślnie zawiera
  samoobsługową funkcję "Usuń konto" w ustawieniach profilu, dostępną dla każdego
  zalogowanego użytkownika. Dla ról **admin** i **trener** ta opcja jest zablokowana/ukryta
  — nikt nie może przypadkowo usunąć własnego konta administratora ani trenera. Usuwanie
  kont personelu (jeśli kiedyś będzie potrzebne) ma być świadomą, osobną funkcją z
  dodatkowymi zabezpieczeniami, a nie czymś dostępnym jednym kliknięciem w ustawieniach.

---

## 9. Jak pracować z Claude w VSC — zasada ogólna

1. Trzymaj plik `spec-klub-fitness.md` w głównym katalogu repozytorium.
2. Na początku **każdego** promptu poniżej wklej: *"Przeczytaj plik spec-klub-fitness.md w repo, to nasza specyfikacja projektu."* — dzięki temu Claude ma pełny kontekst biznesowy, a nie tylko to, co zdąży zapamiętać z czatu.
3. Rób jeden prompt = jeden, względnie mały krok. Po każdym: uruchom aplikację, sprawdź czy działa, dopiero potem przechodź dalej. Nie łącz kilku kroków w jeden wielki prompt — trudniej wtedy złapać błąd i cofnąć się do działającej wersji.
4. Po każdym kroku rób commit w git, zanim przejdziesz do następnego — to Twój "punkt zapisu", do którego zawsze możesz wrócić.

## 10. Gotowe prompty — Faza 1, krok po kroku

**Prompt 1 — inicjalizacja projektu**
```
Przeczytaj plik spec-klub-fitness.md w repo — to specyfikacja projektu, którą będziemy
implementować krok po kroku. Na razie tylko inicjalizacja, bez żadnej logiki biznesowej.

Załóż nowy projekt Laravel z:
- Laravel Breeze (starter kit z Inertia + Vue 3, nie Blade)
- Tailwind CSS (idzie domyślnie z Breeze)
- spatie/laravel-permission do ról (admin/trener/klient)
- bazą MySQL skonfigurowaną w .env (zostaw dane do uzupełnienia przeze mnie)

Po instalacji pokaż mi strukturę katalogów i wyjaśnij krótko gdzie będziemy dodawać
kolejne moduły (models/migrations/controllers) zgodnie z podziałem na domeny
opisanym w sekcji 7 specyfikacji.
```

**Prompt 2 — migracje i modele: klienci i karnety**
```
Przeczytaj spec-klub-fitness.md, sekcję 4 (Model danych) i sekcję 8/8a.

Stwórz migracje i modele Eloquent dla: clients, membership_types, memberships, payments
— dokładnie wg pól opisanych w sekcji 4. Dodaj odpowiednie relacje między modelami
(np. Client hasMany Membership, Membership belongsTo MembershipType).

Nie twórz jeszcze żadnych kontrolerów ani widoków — na tym etapie tylko baza danych
i modele. Na koniec pokaż mi diagram relacji w formie tekstowej, żebym mógł zweryfikować
przed dalszą pracą.
```

**Prompt 3 — dane startowe (seed) wg cennika klubu**
```
Przeczytaj spec-klub-fitness.md. W cenniku klubu (możesz go znaleźć w opisie
membership_types w sekcji 4 i decyzjach w sekcji 8a) mamy następujące typy karnetów:
[tu wklej treść cennika z Image 5 — ceny za 1x/2x/3x/4x w tygodniu, abonament otwarty,
abonament bez limitu, wejście jednorazowe, dodatkowy trening]

Stwórz seeder Laravel, który wypełni tabelę membership_types tymi danymi.
```

**Prompt 4 — panel admina: klienci**
```
Przeczytaj spec-klub-fitness.md. Zaimplementuj panel admina (rola: admin) do zarządzania
klientami: lista, dodawanie, edycja, zmiana statusu aktywny/nieaktywny.
Użyj Inertia + Vue 3, stylowanie Tailwind. Trzymaj się podziału na domeny z sekcji 7
(np. logika w app/Domain/Clients, kontroler cienki).

Nie rób jeszcze przypisywania karnetów — to osobny krok.
```

**Prompt 5 — panel admina: karnety i płatności**
```
Przeczytaj spec-klub-fitness.md, sekcję 8 (zasady biznesowe) i 8a (decyzje).

Dodaj do panelu admina: przypisywanie karnetu (membership) do klienta z listy
membership_types, oraz ręczne rejestrowanie/odhaczanie płatności (status:
oczekuje/zaksięgowana). Pamiętaj, że płatności to wyłącznie przelewy bankowe —
admin ręcznie oznacza wpłatę jako zaksięgowaną po sprawdzeniu wyciągu.

Dodaj też prosty dashboard: karnety kończące się w ciągu 7 dni, niezaksięgowane
płatności, licznik aktywnych abonamentów otwartych w tym miesiącu (informacyjnie,
limit 20 nie jest blokowany przez system — zgodnie z sekcją 8a).
```

Po tych 5 promptach masz działającą Fazę 1. Przetestuj ją realnie (dodaj kilku klientów, przypisz karnety, odhacz płatności) zanim przejdziemy do Fazy 2 — rezerwacji zajęć. To jest bardziej złożona logika (grupy, kolejka wg wpłat, odrabianie), więc lepiej rozbić ją na prompty dopiero po tym, jak zobaczysz Fazę 1 na żywo i ewentualnie coś doprecyzujemy.

## 11. Faza 2, krok 1 — harmonogram zajęć (grafik)

Zanim zajmiemy się rezerwacjami klientów, robimy najpierw narzędzie dla admina do układania grafiku —
zgodnie z wzorcem tygodniowym opisanym w sekcji 4 (`class_groups` → `class_schedule`).

**Prompt 6 — migracje: class_types, class_groups, class_schedule**
```
Przeczytaj spec-klub-fitness.md, sekcję 4 (Model danych), fragment o class_types/class_groups/
class_schedule i logice wzorca miesięcznego.

Stwórz migracje i modele Eloquent dla: class_types, class_groups, class_schedule — dokładnie
wg pól opisanych w sekcji 4. Dodaj relacje (ClassGroup belongsTo ClassType i Trainer,
ClassGroup hasMany ClassSchedule).

Stwórz też seeder dla class_types z przykładowymi typami zajęć: Body Pump, TBC, TBC Max,
HIIT, Fit Dance, Fit Dance Step, Funkcjonal Choreo Step, Mix Treningowy.

Nie twórz jeszcze kontrolerów ani widoków — tylko baza danych, modele i seeder.
```

**Prompt 6a — panel admina: zarządzanie typami zajęć**
```
Przeczytaj spec-klub-fitness.md, sekcję 4 (class_types).

Zaimplementuj w panelu admina prosty CRUD dla typów zajęć (class_types): lista, dodawanie,
edycja, usuwanie. Pola: nazwa, opis, wymaga_sprzetu (opcjonalne, informacyjnie np. "sztangi").

To ma być niezależne od układania grafiku — admin najpierw buduje sobie "słownik" typów
zajęć w tym miejscu, a dopiero w kolejnym kroku (Prompt 7) będzie z niego wybierał przy
układaniu wzorca tygodniowego. Nie dotykaj jeszcze class_groups/class_schedule.
```

**Prompt 6b — kolor i domyślny limit miejsc dla typu zajęć**
```
Przeczytaj spec-klub-fitness.md, sekcję 4 (class_types).

Dodaj do class_types dwa nowe pola (migracja + aktualizacja modelu):
- kolor (hex, np. #E91E63)
- domyslny_limit_miejsc (integer, domyślnie 20)

Rozszerz formularz dodawania/edycji typu zajęć (z Promptu 6a) o:
- wybór koloru przez colorpicker (input type="color" wystarczy na start, nie potrzeba
  zewnętrznej biblioteki)
- pole liczbowe na domyślny limit miejsc, wstępnie wypełnione wartością 20

Na liście typów zajęć w panelu pokaż mały kolorowy znacznik (kropka/pasek) obok nazwy,
żeby kolor był widoczny już na tym etapie — przyda się później przy budowaniu grafiku.

Nie dotykaj jeszcze class_groups/class_schedule — to wciąż tylko słownik typów zajęć.
```

**Prompt 7 — panel admina: edycja wzorca tygodniowego**
```
Przeczytaj spec-klub-fitness.md, sekcję 4 (logika wzorca miesięcznego) i sekcję 11.

Zaimplementuj w panelu admina widok tygodniowego wzorca zajęć (class_groups) w formie
tabeli/kalendarza: dni tygodnia jako kolumny (pon-pt), a w każdej kolumnie lista zajęć
z godziną, typem i limitem miejsc — podobnie jak w grafiku klubu (kilka zajęć dziennie,
różne godziny, jeden trener na start). Każde zajęcia oznacz kolorem przypisanym do ich
typu (class_types.kolor) — dla łatwej wizualnej orientacji w tygodniu.

Admin może: dodać nowe zajęcia do wzorca (dzień, godzina, typ zajęć, limit miejsc —
domyślnie podpowiadany z class_types.domyslny_limit_miejsc, ale edytowalny per konkretne
zajęcia, opcjonalnie zmiana czasu trwania z domyślnych 55 min), edytować istniejące, usunąć.

Na razie NIE implementuj jeszcze generowania class_schedule ani logiki "nowy miesiąc
kopiuje poprzedni" — to osobny krok. Skup się wyłącznie na CRUD wzorca.
```

**Prompt 8a — generowanie harmonogramu miesięcznego z wzorca + widok kalendarza**
```
Przeczytaj spec-klub-fitness.md, sekcję 4 (logika wzorca miesięcznego) i sekcję 11.

Zaimplementuj generowanie harmonogramu miesięcznego (class_schedule) na podstawie
aktualnego wzorca tygodniowego (class_groups) dla wskazanego miesiąca — czyli
"sklonowanie" każdego dnia tygodnia z wzorca na wszystkie pasujące daty w tym miesiącu
(np. każdy poniedziałek w danym miesiącu dostaje kopię zajęć z wzorca "Poniedziałek").

Dodaj w panelu admina przycisk "Wygeneruj harmonogram na [miesiąc]" oraz widok kalendarza
miesięcznego pokazujący wszystkie wygenerowane wystąpienia zajęć (z kolorami wg typu
zajęć, tak jak w widoku tygodniowym wzorca). Kliknięcie w dzień pokazuje listę zajęć
tego dnia.

Jeśli harmonogram dla danego miesiąca był już wygenerowany, ponowne kliknięcie przycisku
nie powinno tworzyć duplikatów — dodaj sensowne zabezpieczenie (np. ostrzeżenie, że
harmonogram już istnieje, z opcją regeneracji).

Nie implementuj jeszcze odwoływania pojedynczych zajęć — to osobny krok (Prompt 8b).
```

**Prompt 8b — odwoływanie pojedynczej instancji zajęć**
```
Przeczytaj spec-klub-fitness.md, sekcję 4 (class_schedule, makeup_credits).

Dodaj w widoku kalendarza miesięcznego (z Promptu 8a) możliwość odwołania pojedynczego
wystąpienia zajęć — np. HIIT w konkretnym dniu, np. 6 września. Kliknięcie "Odwołaj"
przy konkretnych zajęciach: ustawia status = odwolane w class_schedule, prosi o krótki
powód (powod_odwolania), i NIE rusza reszty wzorca ani innych wystąpień tego samego
typu zajęć w innych dniach.

Odwołane zajęcia pokaż w kalendarzu wizualnie inaczej (np. przekreślone/wyszarzone),
zamiast usuwać je z widoku.

Na razie NIE twórz jeszcze makeup_credits dla klientów — nie mamy jeszcze żadnych
rezerwacji klientów w systemie (to osobny, kolejny duży etap). Ten prompt dotyczy
wyłącznie strony admina/harmonogramu.
```

**Prompt 8c — dziedziczenie wzorca i kopiowanie na nowy miesiąc**
```
Przeczytaj spec-klub-fitness.md, sekcję 4 (logika wzorca miesięcznego, dziedziczenie
wzorca w widoku) i sekcję 8a (decyzja: dziedziczenie zamiast automatycznej kopii).

W widoku wzorca tygodniowego (z Promptu 7) rozróżnij dwa stany dla wybranego miesiąca:
- miesiąc własny (ma co najmniej jeden wiersz class_groups z obowiazuje_od w tym
  miesiącu) → pełna edycja, jak dotychczas
- miesiąc dziedziczony (pokazuje wzorzec z wcześniejszego miesiąca, ale żaden wiersz
  nie jest w nim zakotwiczony) → widok tylko do odczytu, wyszarzony, z informacją
  "Wzorzec dziedziczony z: <miesiąc>" i przyciskiem "Skopiuj wzorzec na <ten miesiąc>"

Zaimplementuj kopiowanie (class_groups.copy) przyjmujące miesiąc docelowy: zamyka
wiersze dziedziczone (obowiazuje_do = miesiąc poprzedzający docelowy) i tworzy ich
kopie z obowiazuje_od = miesiąc docelowy, obowiazuje_do = null. Ten sam mechanizm
obsługuje zarówno "skopiuj bieżący na kolejny", jak i "uczyń ten dziedziczony miesiąc
edytowalnym" — z poziomu dowolnego dziedziczonego miesiąca w widoku.

Jeśli miesiąc docelowy ma już własny wzorzec — pokaż ostrzeżenie i nadpisz tylko po
wyraźnym potwierdzeniu.

Przy okazji: popraw nagłówek miesiąca w widoku wzorca — obecnie pokazuje np. "0000 2026"
zamiast czytelnej nazwy miesiąca po polsku (np. "Wrzesień 2026").

Nie zmieniaj logiki generowania class_schedule (Prompt 8a) — to osobny krok, wykonywany
dopiero po zatwierdzeniu/edycji skopiowanego wzorca na nowy miesiąc.
```

## 12. Faza 2, krok 2 — konta klientów i aktywacja dostępu

**Aktywacja klienta — jeden status, dwie ścieżki (MVP, bez automatycznych maili):**

`clients.status` to **jedyny status klienta** (aktywny / nieaktywny). Nowy klient = nieaktywny.
Osobno (nie jako drugi status w UI) istnieje fakt: czy klient ma **skonfigurowane logowanie**
(pole `zaproszenie_wykorzystane_at`) — czyli czy ustawił hasło i może się zalogować. Aktywny
klient **nie musi** mieć skonfigurowanego logowania.

**Ścieżka A — aktywacja ręczna przez admina**
- Na karcie klienta (lub liście) admin klika **„Aktywuj"** → `status = aktywny`. **Nic więcej:**
  bez hasła, bez zgód, bez konfiguracji logowania. Klient jest aktywnym członkiem, ale
  (dopóki nie przejdzie ścieżki B) **nie może się zalogować**.
- Admin w każdej chwili może **„Dezaktywować"** (`status = nieaktywny`) i ponownie „Aktywować".

**Ścieżka B — aktywacja przez klienta z linku**
1. Admin dodaje klienta (formularz: wyłącznie dane podstawowe — imię, nazwisko, email,
   telefon, data urodzenia; **bez** hasła i **bez** checkboxów regulaminu/oświadczenia).
   Nowy klient = nieaktywny. Po zapisie admin trafia od razu na **kartę klienta**.
2. Na karcie klienta admin klika **„Wygeneruj link aktywacyjny"** — system tworzy
   **podpisywany link (Laravel signed URL)**, ważny 7 dni, do publicznej strony aktywacji.
3. Admin **kopiuje link** i wysyła klientowi poza systemem (WhatsApp/Messenger) —
   bez maili automatycznych (to Faza 3).
4. Klient otwiera link i widzi: pełną treść regulaminu (przewijana), checkbox akceptacji
   regulaminu (wymagany), checkbox oświadczenia zdrowotnego (wymagany, pkt 5 regulaminu),
   formularz hasła z potwierdzeniem.
5. Po zatwierdzeniu: hasło zapisane, `regulamin_zaakceptowany_at` + `oswiadczenie_zdrowotne_at`
   + `zaproszenie_wykorzystane_at` wypełnione bieżącą datą, **`status = aktywny`** (klient sam
   się aktywował). Link jednorazowy — od tej pory nieaktywny.
6. Klient zostaje zalogowany i trafia do swojego panelu (na razie pusty/podstawowy).

Link aktywacyjny działa, dopóki `zaproszenie_wykorzystane_at` jest puste — **niezależnie od
statusu**. Czyli po ścieżce A (ręcznej) link nadal można wysłać, żeby klient dokonfigurował
sobie logowanie.

**Prompt 9 — link aktywacyjny i aktywacja konta klienta**
```
Przeczytaj spec-klub-fitness.md, sekcję 4 (pole zaproszenie_wykorzystane_at) i sekcję 12
(pełny opis flow aktywacji konta klienta).

Treść regulaminu do wyświetlenia na stronie aktywacji:
[REGULAMIN ZAJĘĆ FITNESS — wersja obowiązująca, o charakterze wiążącym]

1. Postanowienia ogólne
1. Regulamin określa zasady uczestnictwa w zajęciach fitness organizowanych przez Studio Fitness eMCeFit.
2. Uczestnikiem zajęć może być wyłącznie osoba, która zapoznała się z niniejszym regulaminem i zaakceptowała jego treść.
3. Akceptacja regulaminu następuje poprzez zapisanie się na zajęcia, wykupienie wejścia jednorazowego lub abonamentu.

2. Warunki uczestnictwa
4. Udział w zajęciach możliwy jest po wykupieniu wejścia jednorazowego lub abonamentu zgodnie z obowiązującym cennikiem.
5. Uczestnik oświadcza, że nie posiada przeciwwskazań zdrowotnych do udziału w zajęciach oraz bierze w nich udział na własną odpowiedzialność.
6. Studio Fitness eMCeFit ani instruktorka nie ponoszą odpowiedzialności za urazy, kontuzje ani pogorszenie stanu zdrowia uczestnika powstałe w trakcie zajęć.
7. Jedne zajęcia fitness trwają 55 minut.
8. Podczas zajęć obowiązuje sportowe, zmienne obuwie z czystą podeszwą. Brak odpowiedniego obuwia może skutkować odmową udziału bez prawa do zwrotu opłaty.

3. Abonamenty i rezerwacje
9. Abonament miesięczny z rezerwacją miejsc dotyczy konkretnych, stałych terminów zajęć.
10. Abonament obowiązuje od pierwszego do ostatniego dnia opłaconego miesiąca kalendarzowego i gwarantuje rezerwację miejsca w wybranej grupie.
11. Opłata za abonament obejmuje 4 pełne tygodnie treningowe.
12. W przypadku miesięcy dłuższych niż 4 tygodnie, dodatkowe zajęcia realizowane są bezpłatnie i nie stanowią podstawy do zwiększenia opłaty.
13. W przypadku odwołania pojedynczych zajęć (przy zachowaniu 4 tygodni), opłata pozostaje bez zmian.
13a. Abonamenty krótkoterminowe (np. 2- lub 3-tygodniowe, z rezerwacją miejsca) nie podlegają ciągłości i nie stanowią opcji do stałej kontynuacji z miesiąca na miesiąc. Są to pakiety jednorazowe, przeznaczone np. na okres urlopu lub krótszej dostępności.
14. Niewykorzystane wejścia nie przechodzą na kolejny miesiąc.
15. Nieobecności mogą zostać odrobione wyłącznie w bieżącym miesiącu kalendarzowym, po wcześniejszym zgłoszeniu i tylko na wolnych miejscach w innych grupach.
16. Zwolnienie miejsca należy zgłosić najpóźniej 1 godzinę przed rozpoczęciem zajęć. Brak zgłoszenia skutkuje utratą prawa do odrobienia treningu.
17. Niewykorzystanie wszystkich wejść w abonamencie nie stanowi podstawy do zwrotu środków ani obniżenia kolejnej opłaty.

4. Abonament otwarty
18. Studio Fitness eMCeFit oferuje ograniczoną liczbę abonamentów otwartych – maksymalnie 20 miesięcznie.

Wariant 1 – na ilość wejść:
19. Abonament otwarty obowiązuje przez 5 tygodni od daty pierwszego wejścia.
20. Rozpoczęcie zajęć powinno nastąpić w ciągu 5 dni od daty zakupu.
21. W przypadku braku rozpoczęcia zajęć w tym terminie abonament aktywuje się automatycznie.
22. Abonament nie gwarantuje stałego miejsca w grupie.
23. Uczestnik zapisuje się wyłącznie na wolne miejsca.

Wariant 2 – nielimitowany:
24. Abonament nielimitowany obowiązuje od pierwszego do ostatniego dnia miesiąca kalendarzowego.
25. Uprawnia do nielimitowanego udziału w zajęciach (na zasadzie dostępnych miejsc).
26. Abonament nielimitowany sprzedawany jest wyłącznie na pełny miesiąc kalendarzowy.
27. W przypadku zakupu abonamentu w trakcie miesiąca, obowiązuje pełna opłata zgodnie z cennikiem.
28. Nie ma możliwości przesuwania okresu obowiązywania abonamentu na kolejny miesiąc.

5. Płatności i rezerwacje
29. Opłaty za zajęcia należy dokonywać przelewem na rachunek bankowy: 25 1140 2004 0000 3202 8400 1750
30. Tytuł przelewu: zajęcia fitness, imię i nazwisko + miesiąc, którego dotyczy płatność.

Zasady rezerwacji i płatności:
31. Zgłoszenie chęci udziału w zajęciach oznacza rezerwację miejsca i zobowiązuje do dokonania opłaty.
32. Opłata powinna zostać dokonana w dniu zgłoszenia.
33. Opłata za abonament otwarty musi zostać dokonana niezwłocznie po jego zgłoszeniu.
34. Brak wpłaty skutkuje anulowaniem rezerwacji i możliwością przyznania miejsca innej osobie.
35. Brak opłaty = brak rezerwacji miejsca, niezależnie od wcześniejszego zgłoszenia.
36. O miejscu na liście decyduje kolejność wpłat, nie zgłoszeń.
37. Dopiero po zaksięgowaniu wpłaty uczestnik zostaje wpisany na listę.
38. Abonamenty miesięczne należy opłacić do 28 dnia miesiąca poprzedzającego kolejny miesiąc treningowy.
39. W przypadku braku wpłaty lub potwierdzenia kontynuacji, miejsce zostaje zwolnione.

Weryfikacja listy:
40. Pod koniec każdego miesiąca przeprowadzana jest weryfikacja listy uczestników.
41. Uczestnik zobowiązany jest potwierdzić chęć kontynuacji zajęć.
42. Brak potwierdzenia traktowany jest jako rezygnacja i skutkuje zwolnieniem miejsca.

Zapisz tę treść jako osobny plik/zasób (np. resources/content/regulamin.md albo rekord w bazie
z możliwością edycji przez admina w przyszłości — na razie wystarczy statyczny plik), żeby nie
duplikować jej w kodzie widoku. Strona aktywacji ma go wyświetlać w czytelnej, przewijanej
formie z zachowanym podziałem na sekcje i punkty.

Zaimplementuj:
1. Zmodyfikuj formularz dodawania klienta (z Promptu 4): usuń z niego pole hasła oraz
   wszelkie checkboxy dotyczące regulaminu/oświadczenia zdrowotnego, jeśli istnieją —
   formularz admina ma zawierać wyłącznie dane podstawowe (imię, nazwisko, email,
   telefon, data urodzenia). Nowo utworzony klient dostaje status "nieaktywny" domyślnie.
   Po zapisaniu przekieruj admina na stronę karty klienta (szczegóły tego jednego
   klienta), a NIE na listę klientów.
2. Na stronie karty klienta dodaj przycisk "Wygeneruj link aktywacyjny", który tworzy
   podpisywany link (Laravel signed URL, ważny 7 dni) do publicznej strony aktywacji
   tego konkretnego klienta, i pokazuje go do skopiowania (np. w modalu z przyciskiem
   "Kopiuj").
3. Publiczną stronę aktywacji (dostępną tylko przez poprawny, ważny, niewykorzystany
   podpisany link) z: pełną treścią regulaminu (przewijana sekcja), checkboxem akceptacji
   regulaminu, checkboxem oświadczenia zdrowotnego (oba wymagane do przesłania formularza),
   formularzem ustawienia hasła z potwierdzeniem.
4. Po poprawnym przesłaniu: zapisz regulamin_zaakceptowany_at, oswiadczenie_zdrowotne_at,
   zaproszenie_wykorzystane_at (bieżąca data), ustaw hasło, zaloguj klienta automatycznie
   i przekieruj do jego panelu. Nie ruszaj clients.status — to osobne pole (status
   członkostwa), niezwiązane z dostępem do konta.
5. Link, który wygasł, został już wykorzystany, lub ma nieprawidłowy podpis — pokaż
   czytelny komunikat błędu zamiast łamać stronę.
6. Stwórz podstawowy, pusty na razie dashboard klienta (rola: klient) — wystarczy nagłówek
   powitalny, pełną zawartość dodamy w kolejnych promptach.

Jeśli strona karty klienta jeszcze nie istnieje jako osobny widok (dotąd mogliśmy operować
tylko na liście klientów) — stwórz ją. To dobre miejsce, żeby w przyszłości dodawać kolejne
sekcje (przypisane karnety, historia płatności) na jednym ekranie dot. konkretnego klienta.
```

**Prompt 9a — poprawki: polskie komunikaty błędów + rozdzielenie statusu członkostwa od dostępu do konta**
```
Przeczytaj spec-klub-fitness.md, sekcję 4 (pole zaproszenie_wykorzystane_at — uwaga o
rozróżnieniu od clients.status) i sekcję 12.

1. Skonfiguruj polskie tłumaczenia komunikatów walidacji Laravel — komunikaty typu
   "The email has already been taken." mają się wyświetlać po polsku (np. "Ten adres
   e-mail jest już zajęty.") w całej aplikacji, nie tylko na jednej stronie. Użyj
   standardowego mechanizmu lokalizacji Laravela (pliki lang/pl, APP_LOCALE=pl w .env).

2. Popraw pomyłkę z Promptu 9: obecnie proces aktywacji konta zmienia clients.status
   na "aktywny", co miesza dwa różne pojęcia. Rozdziel je:
   - clients.status (aktywny/nieaktywny) = status członkostwa klienta w klubie,
     ustawiany ręcznie przez admina, NIE ma nic wspólnego z logowaniem
   - dostęp do konta = wyznaczany WYŁĄCZNIE przez to, czy zaproszenie_wykorzystane_at
     jest wypełnione (aktywacja zrobiona) czy puste (czeka na aktywację)

   Cofnij zmianę clients.status w kontrolerze obsługującym aktywację — to pole ma
   zostać nietknięte w tym procesie.

3. Na karcie klienta popraw etykiety, żeby jasno rozróżniały oba pojęcia — np.
   "Status członkostwa: aktywny/nieaktywny" (z clients.status) osobno od
   "Dostęp do konta: aktywne/oczekuje na aktywację" (z zaproszenie_wykorzystane_at).
   Ta sama zasada na liście klientów, jeśli tam też coś się wyświetla.
```

**Prompt 9b — uproszczenie: jeden status + aktywacja ręczna nie konfiguruje logowania**
```
Przeczytaj spec-klub-fitness.md, sekcję 4 (clients.status, zaproszenie_wykorzystane_at)
i sekcję 12 ("Aktywacja klienta — jeden status, dwie ścieżki").

Wycofaj rozdzielenie z Promptu 9a na dwa równorzędne statusy. Zostaje JEDEN status
(clients.status: aktywny/nieaktywny). Konfiguracja logowania (zaproszenie_wykorzystane_at)
to nie drugi status w UI, tylko funkcja/informacja na karcie klienta.

1. Aktywacja ręczna przez admina = TYLKO przełączenie clients.status na "aktywny"
   (przycisk "Aktywuj" na karcie i na liście). Bez ustawiania hasła, bez zgód, bez
   konfiguracji logowania. "Aktywny" nie znaczy "może się zalogować". Odwrotnie:
   "Dezaktywuj" ustawia "nieaktywny".

2. Ścieżka z linkiem (Prompt 9) zostaje, ale po ukończeniu przez klienta USTAWIA
   clients.status = "aktywny" (klient sam się aktywował) — oprócz hasła, zgód i
   zaproszenie_wykorzystane_at. To jedyne miejsce poza ręcznym przełącznikiem, które
   rusza status.

3. Link aktywacyjny działa, dopóki zaproszenie_wykorzystane_at jest puste — niezależnie
   od statusu. Po ścieżce ręcznej (1) przycisk "Wygeneruj link aktywacyjny" nadal
   dostępny, żeby klient dokonfigurował sobie logowanie.

4. UI:
   - Lista klientów: jedna kolumna "Status" (Aktywny/Nieaktywny), akcja "Aktywuj"/
     "Dezaktywuj". Usuń kolumnę/badge "Konto" dodane w 9a.
   - Karta klienta: jeden chip statusu (Aktywny/Nieaktywny) + przycisk "Aktywuj"/
     "Dezaktywuj". Osobno, jako informacja (nie status), sekcja "Logowanie klienta":
     "skonfigurowane (od <data>)" albo "nie skonfigurowane" + przycisk
     "Wygeneruj link aktywacyjny" (widoczny, dopóki logowanie nie skonfigurowane).

Polskie komunikaty walidacji z Promptu 9a zostają bez zmian.
```

**Prompt 9c — zablokowanie usuwania kont admina i trenera**
```
Przeczytaj spec-klub-fitness.md, sekcję 8a (bezpieczeństwo kont personelu).

Domyślny starter kit Laravel Breeze zawiera samoobsługową funkcję "Usuń konto" w
ustawieniach profilu, dostępną dla każdego zalogowanego użytkownika. Zablokuj tę opcję
dla ról admin i trener — formularz/przycisk usuwania konta ma być niewidoczny (albo
wyraźnie zablokowany z komunikatem wyjaśniającym) dla tych dwóch ról. Dla roli klient
funkcjonalność może zostać bez zmian, o ile w ogóle mają dostęp do tego ekranu.

Sprawdź też, czy nie ma żadnej innej ścieżki w aplikacji, którą admin lub trener mógłby
usunąć własne (albo cudze) konto z rolą admin/trener — jeśli tak, zablokuj również ją.
Na tym etapie żadne konto z rolą admin/trener nie powinno dać się usunąć z poziomu UI.
Jeśli w przyszłości pojawi się taka potrzeba, zrobimy to świadomie jako osobną funkcję
z dodatkowymi zabezpieczeniami (np. wymóg potwierdzenia przez innego admina).
```

## 13. Faza 2, krok 3 — zapisy klientów na zajęcia (abonament zamknięty)

**Decyzje dot. tego etapu:**
- Cena karnetu zależy wyłącznie od **liczby wybranych zajęć/tydzień** (dystynktywnych
  `class_groups`), wg cennika (`membership_types`, tryb=zamkniety, sesje_w_tygodniu=N,
  okres_waznosci_typ=miesiac_kalendarzowy) — **niezależnie** od tego, czy klient planuje
  nieobecność w danym tygodniu, czy trener odwołał zajęcia z góry. Cena raz ustalona na
  start miesiąca się nie zmienia.
- **Doprecyzowanie (dodane 2026-09-01):** powyższe dotyczy sytuacji, gdy w danym tygodniu
  klient ma zaplanowaną nieobecność na CZĘŚCI swoich zajęć (np. odpuszcza środę, ale idzie
  na piątek) — tu cena rzeczywiście się nie zmienia, tylko powstaje makeup_credit. Inaczej
  jest, gdy klient planuje pominąć **cały tydzień** (żadnych swoich zajęć w tym tygodniu)
  — wtedy system ma automatycznie dopasować krótszy wariant karnetu z cennika (np. "3x w
  tygodniu — 2 tygodnie" zamiast miesięcznego), jeśli taki wariant istnieje. Szczegóły w
  Prompcie 10e.
- Rekompensata za nieobecność (własną, zaplanowaną z góry, LUB odwołanie przez
  trenera/admina) jest **taka sama**: klient dostaje `makeup_credit`. Na tym etapie MVP
  jest to **tylko licznik** ("masz X zajęć do odrobienia") na dashboardzie klienta —
  samoobsługowe zapisywanie się na odrobienie to zadanie na później (osobny prompt,
  po tym jak ten etap zadziała).
- Klient planuje nieobecność **per pojedyncze zajęcia** (konkretna data), nie "cały
  tydzień naraz" — bo tak działa już istniejący model `makeup_credits`
  (`source_reservation_id` wskazuje na jedną, konkretną rezerwację/wystąpienie).
  W praktyce: jeśli klient ma dwa różne zajęcia w tygodniu, może zaznaczyć nieobecność
  na jednym z nich, a iść na drugie — wybiera osobno dla każdej daty każdych zajęć.

**Uproszczenie na start:** klient wybiera zajęcia i płatność dotyczy **całego bieżącego/
nadchodzącego miesiąca kalendarzowego** (najczęstszy, "domyślny" wariant z cennika).
Zapisy na abonamenty krótkoterminowe (2-3 tyg., pakiety jednorazowe wg pkt 13a
regulaminu) i na abonament otwarty/bez limitu robimy w kolejnych, osobnych promptach —
nie mieszamy ich teraz, żeby nie komplikować pierwszego podejścia do zapisów.

**Prompt 10 — model danych: membership_class_groups**
```
Przeczytaj spec-klub-fitness.md, sekcję 4 (membership_class_groups) i sekcję 13.

Stwórz migrację i model Eloquent dla membership_class_groups (tabela pośrednicząca
między memberships i class_groups — relacja wiele-do-wielu). Jeśli w bazie istnieje
jeszcze stare pole memberships.class_group_id z wcześniejszej wersji modelu — usuń je
migracją i zastąp relacją przez membership_class_groups. Zaktualizuj model Membership
o relację belongsToMany(ClassGroup) przez tę tabelę pośredniczącą.

Nie twórz jeszcze żadnych kontrolerów ani widoków — tylko baza danych i modele.
```

**Prompt 10a — wybór zajęć na miesiąc z kalkulacją ceny na żywo**
```
Przeczytaj spec-klub-fitness.md, sekcję 13 (decyzje dot. zapisów klientów) i sekcję 4
(membership_types, class_groups, membership_class_groups).

Zaimplementuj w panelu klienta stronę "Zapisz się na zajęcia" dla nadchodzącego/bieżącego
miesiąca kalendarzowego:
- Lista dostępnych zajęć cyklicznych (class_groups) w tym miesiącu, pogrupowana wg dni
  tygodnia — dzień, godzina, typ zajęć (z kolorem), liczba wolnych miejsc
- Klient może zaznaczyć dowolną liczbę zajęć (checkboxy), które chce mieć w swoim
  cotygodniowym planie
- Na bieżąco (bez przeładowania strony) licz i pokazuj cenę: liczba zaznaczonych zajęć =
  sesje_w_tygodniu → dopasuj membership_type (tryb=zamkniety, sesje_w_tygodniu=liczba,
  okres_waznosci_typ=miesiac_kalendarzowy) i pokaż jego cenę
- Jeśli liczba zaznaczonych zajęć nie odpowiada żadnemu dostępnemu wariantowi cennika
  (np. więcej niż najwyższy dostępny wariant), pokaż czytelny komunikat zamiast błędu

Nie implementuj jeszcze: oznaczania planowanych nieobecności, przycisku "Zgłoś chęć
udziału"/zapisu do bazy, ani obsługi abonamentu otwartego/krótkoterminowego — to kolejne
kroki.
```

**Prompt 10b — planowane nieobecności i zgłoszenie zapisu**
```
Przeczytaj spec-klub-fitness.md, sekcję 13 (decyzje: rekompensata przez makeup_credit,
cena bez zmian) i sekcję 4 (reservations, makeup_credits).

Rozbuduj stronę z Promptu 10a:
1. Po zaznaczeniu zajęć (class_groups), pokaż pod każdymi z nich listę konkretnych dat
   tego miesiąca (wygenerowanych wcześniej wystąpień z class_schedule). Domyślnie
   wszystkie zaznaczone jako "będę". Klient może odznaczyć pojedynczą datę = "nie będę
   obecny/a" (planowana nieobecność).
2. Daty, które są już odwołane przez klub (class_schedule.status = odwolane), pokaż
   jako informacyjne, wyszarzone "odwołane przez klub" — nie do odznaczenia (są już
   wyłączone).
3. Dodaj przycisk "Zgłoś chęć udziału". Po kliknięciu:
   - utwórz membership (client_id, membership_type_id dopasowany jak w Prompcie 10a,
     data_od/data_do = cały miesiąc kalendarzowy)
   - utwórz wpisy membership_class_groups dla każdego wybranego class_group
   - dla każdej daty oznaczonej jako "będę": utwórz reservation ze statusem
     oczekuje_platnosci
   - dla każdej daty oznaczonej jako "nie będę" ORAZ każdej już odwołanej przez klub:
     utwórz reservation ze statusem odwolana i od razu makeup_credit (wygasa_koniec_miesiaca
     = true, wykorzystany = false)
   - NIE zmieniaj tu jeszcze niczego związanego z płatnością — admin nadal ręcznie
     zaksięgowuje wpłatę (Faza 1, Prompt 5), co potwierdza rezerwacje (już opisane
     w sekcji 4)
4. Po zgłoszeniu przekieruj klienta na stronę potwierdzenia z podsumowaniem: wybrane
   zajęcia, cena, dane do przelewu (numer konta, tytuł — z regulaminu, sekcja 5 pkt 29-30).
```

**Prompt 10c — licznik zajęć do odrobienia na dashboardzie klienta**
```
Przeczytaj spec-klub-fitness.md, sekcję 13.

Dodaj na dashboardzie klienta (z Promptu 9) prosty licznik: "Masz X zajęć do odrobienia"
— suma makeup_credits danego klienta gdzie wykorzystany = false i (wygasa_koniec_miesiaca
= false LUB jeszcze nie minął koniec bieżącego miesiąca). Na razie tylko informacyjnie,
bez możliwości samodzielnego zapisania się na odrobienie — to osobny, kolejny krok.
```

**Prompt 10d — admin ręcznie otwiera zapisy na dany miesiąc**
```
Przeczytaj spec-klub-fitness.md, sekcję 4 (zapisy_miesieczne) i sekcję 13.

Stwórz migrację i model dla nowej tabeli zapisy_miesieczne (rok, miesiac, zapisy_otwarte
domyślnie false, otwarte_od nullable). W panelu admina, na widoku harmonogramu miesięcznego
(z Promptu 8a), dodaj przełącznik "Zapisy otwarte" dla wybranego miesiąca — kliknięcie
tworzy/aktualizuje odpowiedni wiersz w zapisy_miesieczne i ustawia zapisy_otwarte=true
oraz otwarte_od=teraz, z możliwością ponownego zamknięcia.

WAŻNE: generowanie harmonogramu (Prompt 8a) NIE ma otwierać automatycznie zapisów —
to zawsze świadoma, osobna decyzja admina, oddzielna od samego układania grafiku.

Na stronie klienta "Zapisz się na zajęcia" (Prompt 10a/10b) sprawdź flagę zapisy_otwarte
dla wybranego miesiąca PRZED pokazaniem formularza wyboru zajęć. Jeśli zapisy nie są
otwarte, pokaż czytelny komunikat (np. "Zapisy na [miesiąc] nie zostały jeszcze otwarte
przez klub — sprawdź później.") zamiast formularza.
```

**Prompt 10e — automatyczne dopasowanie krótszego wariantu karnetu przy pominiętych całych tygodniach**
```
Przeczytaj spec-klub-fitness.md, sekcję 13 (doprecyzowanie: cały pominięty tydzień vs
pojedyncza nieobecność) i sekcję 4 (membership_types).

Rozbuduj kalkulację ceny na żywo (Prompt 10a) oraz logikę zgłoszenia (Prompt 10b) o
automatyczne dopasowanie krótszego wariantu karnetu, gdy klient planuje pominąć CAŁE
tygodnie (wszystkie swoje wybrane zajęcia w danym tygodniu zaznaczone jako "nie będę"):

1. Dla wybranego miesiąca i zestawu zaznaczonych zajęć, policz "tygodnie z obecnością"
   — tygodnie, w których klient ma zaznaczone przynajmniej jedno "będę" wśród swoich
   wybranych zajęć. Tygodnie, w których WSZYSTKIE wystąpienia zostały już odwołane przez
   klub (nie z wyboru klienta), pomiń całkowicie z tego liczenia (nie liczą się ani na
   plus, ani na minus).
2. Jeśli liczba tygodni z obecnością równa się liczbie wszystkich tygodni zajęć w tym
   miesiącu — użyj wariantu miesięcznego (jak dotychczas, bez zmian).
3. Jeśli jest mniejsza — spróbuj znaleźć membership_type z tryb=zamkniety,
   sesje_w_tygodniu = liczba wybranych zajęć/tydzień, okres_waznosci_typ =
   tygodnie_od_pierwszego_wejscia, okres_waznosci_wartosc = liczba tygodni z obecnością.
   Jeśli taki wariant istnieje w bazie (widzieliśmy w panelu, że są zaseedowane np.
   "3x/tydzień — 2 tygodnie", "3x/tydzień — 3 tygodnie" itd.) — zastosuj go. Liczba
   tygodni z obecnością NIE musi być tygodniami "z rzędu" — klient może pominąć
   dowolny, niekoniecznie ostatni czy pierwszy tydzień, liczy się tylko suma.
   USTAW: data_pierwszego_wejscia = data pierwszego "będę" w miesiącu, data_do = data
   ostatniego "będę" w tym miesiącu (NIE "pierwsze wejście + N tygodni" — to by błędnie
   zawężało zakres przy nie-kolejnych tygodniach, np. gdy klient ma zajęcia w tygodniu 1
   i 4, ale pomija 2-3).
4. Jeśli nie ma dopasowanego wariantu (np. przy 1 zajęciu/tydzień nie ma krótszych
   pakietów w cenniku, albo liczba tygodni z obecnością nie ma odpowiednika) — zastosuj
   wariant miesięczny jako bezpieczny fallback i pokaż klientowi czytelną informację:
   "Wybrany wzorzec obecności nie pasuje do żadnego krótszego pakietu — zastosowano cenę
   pełnego miesiąca."
5. Pokazuj cenę na żywo w trakcie zaznaczania (jak w Prompcie 10a), z krótkim opisem
   dopasowanego wariantu (np. "3x w tygodniu — 2 tygodnie: 130 zł" zamiast samej kwoty).

Pamiętaj: to dotyczy WYŁĄCZNIE całych pominiętych tygodni. Pojedyncza pominięta data w
tygodniu, w którym klient i tak ma inne zajęcia, nadal działa jak dotychczas (bez zmiany
ceny, tworzy makeup_credit) — nie miesza się z tą logiką.
```

**Prompt 10f — wizualne wyróżnienie liczby zajęć do odrobienia na ekranie zapisów**
```
Przeczytaj spec-klub-fitness.md, sekcję 13 (makeup_credits, dopasowanie wariantu).

Na stronie "Zapisz się na zajęcia" (Prompt 10a/10b/10e), w panelu podsumowania ceny,
wyróżnij wizualnie liczbę zajęć, za które klient dostanie prawo do odrobienia (czyli
liczbę zaznaczonych "nie będę" + już odwołanych przez klub, dla wybranych zajęć w danym
miesiącu). Obecnie ta informacja jest ukryta w zdaniu tekstowym ("Terminy: X będę, Y nie
będę...") i łatwo ją przeoczyć.

Zrób z tego osobny, widoczny element — np. plakietkę/badge z ikoną obok ceny, w stylu
"+Y zajęć do odrobienia", innym kolorem niż reszta podsumowania (spójnym z tym, co już
jest użyte do ostrzeżenia o cenie pełnego miesiąca). Zostaw pełne zdanie z terminami
poniżej jako szczegóły — dodaj coś, co rzuca się w oczy na pierwszy rzut oka, zamiast
chować się w drobnym druku.
```

**Prompt 10g — nie pokazuj formularza zapisów, gdy zgłoszenie na dany miesiąc już istnieje**
```
Przeczytaj spec-klub-fitness.md, sekcję 13.

Popraw stronę "Zapisz się na zajęcia" (Prompt 10a/10b/10e): gdy klient ma już zgłoszenie
(membership) na wybrany miesiąc, NIE pokazuj w ogóle formularza wyboru zajęć poniżej
komunikatu "Masz już zgłoszenie na ten miesiąc." — obecnie formularz nadal się renderuje
pod spodem, co jest mylące (wygląda, jakby można było zgłosić się drugi raz).

Zostaw tylko: komunikat + link "Zobacz szczegóły swojego zgłoszenia" prowadzący do
"Moje zajęcia" (Prompt 12). Formularz wyboru zajęć ma się pokazywać wyłącznie wtedy, gdy
klient NIE ma jeszcze żadnego zgłoszenia na wybrany miesiąc.
```

---

## 14. Edycja cen karnetów przez admina

Zawężona wersja punktu z backlogu Fazy 3 — na razie tylko **cena**, reszta atrybutów karnetu
(nazwa, tryb, częstotliwość, sposób liczenia ważności) zostaje jako dane startowe (seed),
edytowalne dopiero w pełnym CRUD w dalszej przyszłości.

**Prompt 11 — edycja ceny karnetów (tylko cena)**
```
Przeczytaj spec-klub-fitness.md, sekcję 4 (membership_types) i sekcję 14.

Dodaj w panelu admina prosty widok listy membership_types z możliwością edycji WYŁĄCZNIE
pola cena — reszta pól (nazwa, tryb, sesje_w_tygodniu, liczba_wejsc, okres_waznosci_typ,
okres_waznosci_wartosc) ma być widoczna, ale tylko do odczytu na tym etapie. Zapisz zmianę
ceny od razu po edycji (inline edit albo prosty formularz na wiersz).

Zmiana ceny wpływa TYLKO na nowe karnety zakładane od tego momentu — nie zmieniaj
retroaktywnie ceny już istniejących, opłaconych lub oczekujących na płatność karnetów
(memberships), żeby nie zaburzyć rozliczeń w toku.
```

**Prompt 11a — migawka ceny zapisywana w momencie założenia karnetu**
```
Przeczytaj spec-klub-fitness.md, sekcję 4 (memberships: cena_ustalona) i sekcję 14.

To poprawka na przyszłość: dotąd cena karnetu była tylko wyliczana "na żywo" z
membership_types.cena — a to pole może się zmienić (Prompt 11), co retroaktywnie
zniekształcałoby cenę widoczną przy już istniejących, archiwalnych karnetach.

Dodaj do memberships nowe pole: cena_ustalona (decimal) — zapisywane RAZ, w momencie
utworzenia karnetu (Prompt 10b i każde inne miejsce, gdzie membership jest tworzony),
jako migawka ceny obowiązującej w danym momencie (z dopasowanego membership_type,
uwzględniając ewentualne dopasowanie krótszego wariantu z Promptu 10e).

Zaktualizuj wszystkie miejsca w aplikacji, które dotąd pokazywały cenę karnetu przez
relację do membership_type.cena (karta klienta z Promptu 16a/16b, panel admina,
dashboard/"Moje zajęcia" klienta) — mają pokazywać memberships.cena_ustalona zamiast
ceny live z membership_type. Dzięki temu zmiana ceny w cenniku nigdy nie wpłynie na to,
co widać przy już istniejących, opłaconych lub archiwalnych karnetach.

Dla rekordów memberships, które już istnieją w bazie z testów — uzupełnij cena_ustalona
jednorazowo aktualną ceną z ich membership_type (to i tak dane testowe).
```

---

## 15. Podgląd własnego zgłoszenia (klient) — "Moje zajęcia"

**Prompt 12 — strona "Moje zajęcia" dla klienta**
```
Przeczytaj spec-klub-fitness.md, sekcję 4 (memberships, membership_class_groups,
reservations, makeup_credits) i sekcję 13.

Dodaj w panelu klienta stronę "Moje zajęcia" pokazującą jego aktualne zgłoszenie na
bieżący/nadchodzący miesiąc (jeśli istnieje): wybrane zajęcia (class_groups), status
płatności karnetu (payments.status), status każdej rezerwacji (reservations.status) wraz
z datami, oraz licznik zajęć do odrobienia (z Promptu 10c — możesz go tu przenieść/
zlinkować zamiast duplikować logiki).

Podlinkuj tę stronę z komunikatu "Masz już zgłoszenie na ten miesiąc." pokazywanego przy
próbie ponownego zapisu (Prompt 10b) — np. link "Zobacz szczegóły" prowadzący właśnie
tutaj.

Jeśli klient nie ma jeszcze żadnego zgłoszenia na dany miesiąc, pokaż zachętę do
zapisania się (link do strony z Promptu 10a).
```

**Prompt 12a — wizualne wyszarzenie minionych zajęć**
```
Przeczytaj spec-klub-fitness.md, sekcję 15.

Na stronie "Moje zajęcia" (Prompt 12), oprócz istniejącego dopisku "Zajęcia odbyły się"
przy zajęciach z przeszłości, wyszarz wizualnie CAŁY wiersz/kartę takich zajęć (np.
zmniejszona nieprzezroczystość, jaśniejszy tekst) — tak, żeby klientka od razu, na
pierwszy rzut oka, odróżniała minione zajęcia od nadchodzących, bez czytania każdego
dopisku osobno. Zajęcia nadchodzące zostają w pełnej, normalnej kolorystyce.
```

---

## 16. Faza 2, krok 4 — potwierdzanie rezerwacji i limit miejsc/waitlista

To jest domknięcie "kluczowej reguły" z sekcji 3 i 4: rezerwacja jest potwierdzona
dopiero po zaksięgowaniu wpłaty, a kolejność na liście/liście oczekujących wynika z
daty zaksięgowania płatności, nie zgłoszenia. Do tej pory mieliśmy osobno: rezerwacje
ze statusem `oczekuje_platnosci` (z Promptu 10b) i ręczne odhaczanie płatności (z Fazy 1,
Prompt 5) — ale nic ich ze sobą nie łączyło. Ten krok to naprawia.

**Prompt 13 — potwierdzanie rezerwacji przy zaksięgowaniu płatności + limit miejsc/waitlista**
```
Przeczytaj spec-klub-fitness.md, sekcję 4 (uwaga o kolejności wg daty zaksięgowania) i
sekcję 16.

Rozbuduj akcję "oznacz płatność jako zaksięgowana" (z Fazy 1, Prompt 5) o:
1. Ustaw payments.data_zaksiegowania = teraz (jeśli jeszcze nie ustawione).
2. Znajdź wszystkie reservations powiązane z tym samym membership_id, które mają status
   oczekuje_platnosci.
3. Dla każdej z nich (w kolejności dat zajęć rosnąco), sprawdź limit miejsc zajęć
   (class_schedule → class_group → limit_miejsc) — policz obecną liczbę reservations
   ze statusem potwierdzona dla tego samego class_schedule_id:
   - jeśli jest wolne miejsce: ustaw status = potwierdzona, data_potwierdzenia =
     payments.data_zaksiegowania
   - jeśli brak miejsca: ustaw status = waitlist, data_potwierdzenia =
     payments.data_zaksiegowania (data zaksięgowania mimo to zapisana — to ona decyduje
     o kolejności na liście oczekujących względem innych klientów, zgodnie z
     regulaminem pkt 36)
4. Pokaż adminowi krótkie podsumowanie po zaksięgowaniu płatności (np. "3 zajęcia
   potwierdzone, 1 na liście oczekujących: [nazwa zajęć, data]").

Na razie NIE implementuj jeszcze promowania z listy oczekujących po zwolnieniu miejsca
(np. gdy ktoś anuluje już potwierdzoną rezerwację) — nie mamy jeszcze takiej ścieżki
anulowania po stronie klienta. To osobny, kolejny krok, po tym jak ten zadziała.
```

**Prompt 13a — panel admina: podgląd listy zapisanych/oczekujących per zajęcia**
```
Przeczytaj spec-klub-fitness.md, sekcję 4 (reservations) i sekcję 16.

Dodaj w panelu admina, w widoku konkretnego wystąpienia zajęć (class_schedule) w
kalendarzu miesięcznym (z Promptu 8a), listę zapisanych klientów: imię i nazwisko,
status rezerwacji (potwierdzona/waitlist/oczekuje_platnosci), data zgłoszenia, data
potwierdzenia (jeśli jest). Klienci na liście oczekującej wyraźnie oznaczeni (np.
"Lista oczekujących (2)") i posortowani wg daty potwierdzenia rosnąco — czyli kto z tej
grupy pierwszy zapłacił, ten wyżej na liście oczekujących.
```

---

---

## 17. Faza 2, krok 5 — odwoływanie rezerwacji przez klienta i promowanie z waitlisty

To domyka pętlę rezerwacji odłożoną w Prompcie 13 ("nie mamy jeszcze ścieżki anulowania
po stronie klienta"). Bez tego lista oczekujących nigdy się nie porusza — nikt nie
dostaje zwolnionego miejsca.

**Prompt 14 — klient odwołuje potwierdzoną rezerwację**
```
Przeczytaj spec-klub-fitness.md, sekcję 8 (regulamin: pkt 15-16, odwołanie min. 1h przed
zajęciami) i sekcję 4 (reservations, makeup_credits).

Na stronie "Moje zajęcia" (Prompt 12) dodaj przycisk "Odwołaj" przy każdej nadchodzącej
rezerwacji ze statusem potwierdzona (tylko dla zajęć, które jeszcze się nie odbyły — nie
pokazuj przycisku przy zajęciach z przeszłości). Po kliknięciu:

1. Jeśli do rozpoczęcia zajęć zostało co najmniej 1 godzina: ustaw reservation.status =
   odwolana i utwórz makeup_credit (wygasa_koniec_miesiaca = true, wykorzystany = false,
   source_reservation_id = ta rezerwacja). Pokaż potwierdzenie: "Zajęcia odwołane, masz
   teraz prawo do odrobienia w tym miesiącu."
2. Jeśli zostało mniej niż 1 godzina: przed odwołaniem pokaż wyraźne ostrzeżenie —
   "Zgodnie z regulaminem, odwołanie później niż godzinę przed zajęciami nie daje prawa
   do odrobienia" — z opcją potwierdzenia (odwołuje miejsce, ALE bez makeup_credit) albo
   wycofania się.

Nie implementuj jeszcze promowania kogoś z listy oczekujących po zwolnieniu miejsca —
to osobny krok (Prompt 14a), żeby nie łączyć dwóch rzeczy w jednym prompcie.
```

**Prompt 14a — promowanie z listy oczekujących po zwolnieniu miejsca**
```
Przeczytaj spec-klub-fitness.md, sekcję 4 (uwaga o kolejności wg daty zaksięgowania) i
sekcję 17.

Po odwołaniu rezerwacji przez klienta (Prompt 14), sprawdź czy dla tego samego
class_schedule_id istnieje ktoś ze statusem waitlist. Jeśli tak, znajdź osobę z
najwcześniejszą data_potwierdzenia (czyli kto pierwszy z listy oczekujących
faktycznie zapłacił — regulamin pkt 36) i zmień jej reservation.status na potwierdzona.

Napisz tę logikę jako reużywalną metodę/serwis (nie wklejaj jej bezpośrednio w kontroler
odwołania) — w przyszłości będzie prawdopodobnie wywoływana też z innych miejsc (np. gdy
admin cofnie zaksięgowanie płatności).

Na tym etapie NIE wysyłamy żadnego powiadomienia promowanej osobie (brak automatycznych
maili w MVP) — zobaczy zaktualizowany status przy następnym wejściu na "Moje zajęcia".
```

**Prompt 14b — zmiana nazewnictwa: "zwolnienie miejsca" zamiast "odwołanie"**
```
Przeczytaj spec-klub-fitness.md, sekcję 4 (status rezerwacji: zwolnione) i sekcję 17.

Ujednolić nazewnictwo w całej aplikacji (kod, UI, komunikaty) — zamiast "odwołanie
zajęć przez klienta" używamy terminu wprost z regulaminu: "zwolnienie miejsca"
(regulamin, pkt 16). Konkretnie:

1. Zmień wartość statusu rezerwacji z "odwolana" na "zwolnione" (migracja zmieniająca
   wartości w bazie + aktualizacja wszystkich miejsc w kodzie, które się do niej
   odwołują — logika z Promptu 10b, Promptu 14, Promptu 14a, dashboard klienta,
   panel admina).
2. Przycisk "Odwołaj" na stronie "Moje zajęcia" (Prompt 14) zmień na "Zwolnij miejsce".
3. Komunikat "Zajęcia odwołane, masz teraz prawo do odrobienia" zmień na "Miejsce
   zwolnione, masz teraz prawo do odrobienia w tym miesiącu."
4. Wszędzie, gdzie w UI (panel admina, panel klienta) wyświetlany jest status rezerwacji
   "odwołana", zmień etykietę na "zwolnione".

WAŻNE: to NIE dotyczy statusu class_schedule (planowane/odwolane) — to osobna rzecz
(admin odwołuje CAŁE zajęcia dla wszystkich zapisanych), zostaje bez zmian. Zmiana
dotyczy WYŁĄCZNIE statusu pojedynczej rezerwacji klienta.
```

---

## 18. Ikony i oznaczenia wizualne typów zajęć

**Prompt 15 — ikony dla typów zajęć**
```
Przeczytaj spec-klub-fitness.md, sekcję 4 (class_types: kolor) i sekcję 18.

Dodaj do class_types nowe pole: ikona (string — nazwa ikony z biblioteki ikon). Jeśli w
projekcie nie ma jeszcze żadnej biblioteki ikon, zainstaluj lucide-vue-next (lekka,
duży zestaw pasujących ikon: hantle/ciężarki, serce/puls, nuty, stopa/bieg itp.).

Rozszerz formularz dodawania/edycji typu zajęć (Prompt 6a/6b) o wybór ikony — prosty
picker/grid z kilkunastoma sensownymi ikonami do wyboru (np. hantle dla zajęć siłowych,
serce-puls dla cardio/HIIT, nuty dla tanecznych, stopa dla ogólnych/mix). Ustaw sensowny
domyślny wybór dla nowo tworzonych typów, a dla już istniejących (Body Pump, TBC, HIIT
itd.) dobierz pasujące ikony i zapisz je w seederze/migracji danych.
```

**Prompt 15a — kwadracik z ikoną zamiast kropki w całej aplikacji**
```
Przeczytaj spec-klub-fitness.md, sekcję 4 (class_types: kolor, ikona) i sekcję 18.

Wszędzie w aplikacji, gdzie obok nazwy zajęć pokazywana jest kolorowa kropka (wzorzec
tygodniowy z Promptu 7, kalendarz miesięczny z Promptu 8a, strona "Zapisz się na zajęcia"
z Promptu 10a, strona "Moje zajęcia" z Promptu 12) — zamień okrągłą kropkę na mały
kwadracik/plakietkę (zaokrąglony róg, nie koło) w kolorze typu zajęć, z ikoną tego typu
zajęć w środku (biała/kontrastowa ikona na kolorowym tle).

Chodzi o to, żeby nie wyglądało to jak wskaźnik statusu (zielony/czerwony kojarzy się z
"aktywne/nieaktywne"), tylko jak neutralna etykieta wizualna danego typu zajęć — kolor +
ikona razem, w jednym miejscu, a nie osobno.
```

---

## 19. Karta klienta jako centralny widok (dla admina)

**Prompt 16 — klikalne nazwiska prowadzące do karty klienta**
```
Przeczytaj spec-klub-fitness.md, sekcję 12 (karta klienta, /admin/clients/{id}) i
sekcję 19.

W panelu admina, wszędzie gdzie wyświetlane jest imię i nazwisko klienta na jakiejkolwiek
liście (lista zapisanych/oczekujących przy konkretnych zajęciach z Promptu 13a, lista
klientów z Promptu 4, i wszelkie inne miejsca, gdzie klient pojawia się po imieniu i
nazwisku) — zamień to na link prowadzący do jego karty klienta (/admin/clients/{id}).
```

**Prompt 16a — rozbudowa karty klienta o pełną historię aktywności**
```
Przeczytaj spec-klub-fitness.md, sekcję 4 (reservations, makeup_credits, payments) i
sekcję 19.

Rozbuduj kartę klienta (Prompt 9, dotąd: dane podstawowe, dostęp do konta, status
członkostwa, przypisywanie karnetu, płatności) o pełny przegląd jego aktywności, w
czytelnych, osobnych sekcjach na tej samej stronie:

1. "Zapisy na zajęcia" — lista wszystkich jego reservations (bieżący i poprzednie
   miesiące), z: nazwą i datą zajęć, statusem (potwierdzona/waitlist/zwolnione/odrobiona),
   datą zgłoszenia i datą potwierdzenia. Nadchodzące/najnowsze na górze.
2. "Zajęcia do odrobienia" — lista makeup_credits tego klienta (data źródłowej
   rezerwacji, czy wykorzystany, czy już wygasł).
3. "Historia płatności" — jeśli już istnieje na karcie klienta z wcześniejszych promptów,
   upewnij się, że jest na tej samej stronie w osobnej sekcji, a nie oddzielnie.

Cel: admin ma tu widzieć "wszystko, co wiadomo o kliencie" w jednym miejscu, bez
przeskakiwania między ekranami.
```

**Prompt 16b — zakładki miesięcy i podsumowanie tygodniowe na karcie klienta**
```
Przeczytaj spec-klub-fitness.md, sekcję 19 (karta klienta) i sekcję 13 (widok "wybrane
zajęcia co tydzień" u klienta).

Rozbuduj kartę klienta (Prompt 16a) o dwie rzeczy:

1. Nad listą "Zapisy na zajęcia" dodaj sekcję "Wybrane zajęcia (co tydzień)" — dokładnie
   taki sam widok, jaki klientka widzi na swojej stronie "Moje zajęcia" (Prompt 12): dzień
   tygodnia, godzina, plakietka typu zajęć (kolor + ikona, z Promptu 15a). To pokazuje
   adminowi w skrócie, na jaki wzorzec tygodniowy klientka jest zapisana w danym
   miesiącu, bez przewijania całej listy dat.

2. Dodaj zakładki miesięcy nad sekcją "Zapisy na zajęcia" — tak jak już mamy na stronie
   "Zapisz się na zajęcia" (Prompt 10a): zakładki typu "Wrzesień 2026", "Październik
   2026". Domyślnie otwarty ma być bieżący/aktywny miesiąc klientki. Wcześniejsze
   miesiące (archiwalne) też mają być dostępne do przejrzenia — ich lista zapisów, cena
   i status płatności z tamtego okresu — wyłącznie do odczytu, bez możliwości edycji.
```

**Prompt 16c — potwierdzenie przy dezaktywacji klienta**
```
Przeczytaj spec-klub-fitness.md, sekcję 19 (karta klienta).

Na karcie klienta przycisk "Dezaktywuj" (zmiana clients.status na nieaktywny) ma
wymagać potwierdzenia w oknie/alercie przed wykonaniem akcji — np. "Czy na pewno chcesz
dezaktywować tego klienta?" z przyciskami Potwierdź/Anuluj. Dopiero po potwierdzeniu
wykonaj zmianę statusu. To proste zabezpieczenie przed przypadkowym kliknięciem.
```
