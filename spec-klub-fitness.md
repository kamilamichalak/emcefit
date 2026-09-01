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
- **Karta klienta** (widok szczegółów `/admin/clients/{id}`) — pojedyncze miejsce pracy z klientem: podsumowanie (aktywny karnet, saldo wpłat, wpłaty oczekujące), historia wykupionych karnetów oraz chronologiczna historia wszystkich płatności z akcjami zmiany statusu. Ekran edycji to sam formularz danych, z linkiem do karty.
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
- Edycja cennika/typów karnetów przez admina w panelu (w Fazie 1 to dane startowe/seed)

---

## 4. Model danych (szkic)

```
clubs
 └─ id, nazwa, dane_kontaktowe

users              -- wspólna tabela logowania (admin/trener/klient)
 └─ id, imie, nazwisko, email, hash_hasla, rola, club_id

clients            -- rozszerzenie usera o dane specyficzne dla klienta
 └─ id, user_id, telefon, data_urodzenia, status, data_dolaczenia,
    regulamin_zaakceptowany_at, oswiadczenie_zdrowotne_at

trainers           -- rozszerzenie usera o dane trenera
 └─ id, user_id, specjalizacja

membership_types   -- rodzaje karnetów wg cennika klubu, konfigurowane przez admina
 └─ id, nazwa, tryb (zamkniety / otwarty / bez_limitu / jednorazowe),
    sesje_w_tygodniu (nullable), liczba_wejsc (nullable),
    okres_waznosci_typ (miesiac_kalendarzowy / tygodnie_od_pierwszego_wejscia),
    okres_waznosci_wartosc, cena

memberships        -- karnet przypisany klientowi
 └─ id, client_id, membership_type_id, class_group_id (nullable, tylko tryb "zamknięty"),
    data_pierwszego_wejscia, data_od, data_do, wejscia_pozostale,
    kontynuacja_potwierdzona (bool, resetowane co miesiąc)

payments
 └─ id, client_id, membership_id, kwota, data_zgloszenia,
    data_zaksiegowania (nullable), status (oczekuje/zaksiegowana/anulowana), tytul_przelewu

class_types        -- np. joga, crossfit, zumba
 └─ id, nazwa, opis, czas_trwania_min (domyślnie 55)

class_groups       -- stały, powtarzalny termin tygodniowy dla abonamentów zamkniętych
 └─ id, class_type_id, trainer_id, dni_tygodnia, godzina, limit_miejsc

class_schedule     -- konkretne wystąpienie zajęć (generowane z class_groups + doraźne dla abonamentu otwartego)
 └─ id, class_group_id (nullable), class_type_id, trainer_id, data_godzina, limit_miejsc

reservations
 └─ id, client_id, class_schedule_id, membership_id,
    status (oczekuje_platnosci/potwierdzona/waitlist/odwolana/odrobiona),
    data_zgloszenia, data_potwierdzenia (= data zaksięgowania powiązanej płatności)

makeup_credits     -- odrobienia po odwołaniu zajęć w ramach abonamentu zamkniętego
 └─ id, client_id, source_reservation_id, wygasa_koniec_miesiaca (bool), wykorzystany (bool)
```

To jest szkic pod Fazę 1+2 — nie modelujemy jeszcze planów treningowych (Faza 3).

**Uwaga:** kolejność w `reservations`/waitliście ustalana jest po `data_potwierdzenia` (czyli po zaksięgowaniu wpłaty), a nie po `data_zgloszenia` — to bezpośrednio z regulaminu (pkt 36: "o miejscu na liście decyduje kolejność wpłat, nie zgłoszeń").

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
- **Karta klienta jako hub** (dodane 2026-09-01) — historia karnetów i płatności klienta żyje na ekranie szczegółów, nie jest duplikowana pod formularzem edycji; „aktywny karnet" = opłacony (min. 1 płatność zaksięgowana) i mieszczący się w dacie ważności; „saldo wpłat" liczy tylko płatności zaksięgowane

**Założenie robocze** (do potwierdzenia, ale przyjmuję jako rozsądny domyślny wybór): przy pierwszej rejestracji/zakupie karnetu w systemie klient zaznacza checkbox "zapoznałem się z regulaminem" i "oświadczam brak przeciwwskazań zdrowotnych" — to prosty do wdrożenia ślad prawny (pola `regulamin_zaakceptowany_at`, `oswiadczenie_zdrowotne_at` już są w modelu `clients`). Daj znać, jeśli wolisz to zostawić poza systemem.

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

Napisz do mnie, jak skończysz Fazę 1 — przygotuję wtedy analogiczną sekwencję promptów dla Fazy 2 (grafik, rezerwacje, waitlista, odrabianie zajęć).
