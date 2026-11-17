<x-mail::message>
# Cześć {{ $firstName }}!

Zapisy na **{{ $monthLabel }}** są już otwarte. Zaloguj się i wybierz swoje zajęcia — jeśli chodzisz na to samo co teraz, wystarczy jedno kliknięcie „Zapisz mnie na te same zajęcia”.

<x-mail::button :url="$url">
Zapisz się na {{ $monthLabel }}
</x-mail::button>

Miejsca potwierdzamy po wpłacie — kto pierwszy wpłaci, ten ma pewne miejsce.

Do zobaczenia na zajęciach!<br>
Studio eMCeFit
</x-mail::message>
