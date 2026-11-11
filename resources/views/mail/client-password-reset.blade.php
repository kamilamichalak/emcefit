<x-mail::message>
# Cześć {{ \Illuminate\Support\Str::before($name, ' ') }}!

Dostałyśmy prośbę o zmianę hasła do Twojego konta w systemie zapisów Studio eMCeFit.

<x-mail::button :url="$link">
Ustaw nowe hasło
</x-mail::button>

Link jest ważny {{ \App\Domain\Clients\Actions\SendPasswordResetLink::LINK_HOURS }} godziny. Po ustawieniu hasła od razu zalogujesz się do panelu.

Jeśli to nie Ty prosiłaś o zmianę hasła, zignoruj tę wiadomość — Twoje obecne hasło dalej działa.

Do zobaczenia na zajęciach!<br>
Studio eMCeFit
</x-mail::message>
