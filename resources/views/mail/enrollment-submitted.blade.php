<x-mail::message>
# Cześć {{ $firstName }}!

Twoje zgłoszenie na **{{ $monthLabel }}** jest przyjęte.

**Karnet:** {{ $typeName }} — **{{ $price }}**

@if ($classes->isEmpty())
Zajęcia wybierasz sama w aplikacji — zakładka „Wolne miejsca”.
@else
**Twoje zajęcia (co tydzień):**
@foreach ($classes as $class)
- {{ $class }}
@endforeach
@endif

<x-mail::panel>
**Wpłać do {{ $deadline }}** — przelewem albo na miejscu w studiu.

Brak wpłaty w terminie oznacza usunięcie z listy zajęć.

Numer konta: **{{ $account }}**<br>
Tytuł: {{ $title }}
</x-mail::panel>

Miejsca potwierdzimy po zaksięgowaniu wpłaty — dostaniesz wtedy maila.

<x-mail::button :url="$url">
Moje zajęcia
</x-mail::button>

Do zobaczenia na zajęciach!<br>
Studio eMCeFit

<x-mail::subcopy>
Nie chcesz takich maili? [Zmień ustawienia powiadomień]({{ route('client.mail-preferences.edit') }}).
</x-mail::subcopy>
</x-mail::message>
