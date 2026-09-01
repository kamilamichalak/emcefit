# app/Domain — podział na domeny (sekcja 7 specyfikacji)

Logika biznesowa mieszka tutaj, pogrupowana w domeny. Kontrolery w
`app/Http/Controllers` mają być **cienkie** — walidują request, wołają klasę
akcji/serwisu z odpowiedniej domeny i zwracają odpowiedź Inertia.

Namespace `App\Domain\...` mapuje się 1:1 na ten katalog (PSR-4, `App\` -> `app/`),
więc nie trzeba nic dopisywać w `composer.json`.

## Domeny i ich odpowiedniki w modelu danych (sekcja 4)

| Katalog | Tabele / odpowiedzialność |
|---|---|
| `Club/`         | `clubs` — dane klubu, ustawienia globalne |
| `Clients/`      | `clients` — kartoteka klienta, zgody (regulamin, oświadczenie zdrowotne), status |
| `Trainers/`     | `trainers` — dane trenera, specjalizacja (relacja klub ↔ trenerzy 1-do-wielu) |
| `Memberships/`  | `membership_types`, `memberships` — cennik/typy karnetów + karnet przypisany klientowi |
| `Payments/`     | `payments` — ręczna rejestracja przelewów, księgowanie wpłat |
| `Scheduling/`   | `class_types`, `class_groups`, `class_schedule` — grafik i wystąpienia zajęć |
| `Reservations/` | `reservations`, `makeup_credits` — rezerwacje, waitlista, odrabianie zajęć |
| `Shared/`       | kod współdzielony między domenami (enumy, value objecty, wspólne kontrakty) |

## Sugerowana struktura wewnątrz domeny

```
app/Domain/<Domena>/
  Models/        # modele Eloquent (np. App\Domain\Clients\Models\Client)
  Actions/       # pojedyncze operacje biznesowe (np. RegisterClient, MarkPaymentAsSettled)
  Data/          # DTO / obiekty przenoszące dane z requestów
  Enums/         # np. MembershipMode, PaymentStatus, ReservationStatus
```

Migracje pozostają w `database/migrations/` (globalna historia schematu),
seedery w `database/seeders/`, fabryki w `database/factories/` — Laravel tego
wymaga i nie rozbijamy tego per domena.

Testy kluczowej logiki biznesowej: `tests/Feature/<Domena>/...`
(np. „klient bez ważnego karnetu nie może zarezerwować zajęć”).
