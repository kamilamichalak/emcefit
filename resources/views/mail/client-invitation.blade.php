<x-mail::message>
# Cześć {{ \Illuminate\Support\Str::before($name, ' ') }}!

Zapraszamy do nowego systemu zapisów na zajęcia w Studio eMCeFit. Znajdziesz w nim swój karnet, swoje zajęcia i zapisy na kolejny miesiąc.

Kliknij przycisk, ustaw hasło i zaakceptuj regulamin — to zajmie minutę.

<x-mail::button :url="$link">
Aktywuj konto
</x-mail::button>

Link jest ważny {{ \App\Domain\Clients\Models\Client::ACTIVATION_LINK_DAYS }} dni — możesz do niego wracać, aż ustawisz hasło. Później logujesz się już swoim e-mailem i hasłem.

Do zobaczenia na zajęciach!<br>
Studio eMCeFit
</x-mail::message>
