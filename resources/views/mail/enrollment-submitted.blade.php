<x-mail::message>
# Cześć {{ $firstName }}!

Twoje zgłoszenie na **{{ $monthLabel }}** jest przyjęte.

**Karnet:** {{ $typeName }} — **{{ $price }}**

**Twoje zajęcia (co tydzień):**
@foreach ($classes as $class)
- {{ $class }}
@endforeach

<x-mail::panel>
**Wpłać w ciągu 3 dni (do {{ $deadline }})** — przelewem albo na miejscu w studiu.

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
