<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const loggedIn = computed(() => Boolean(usePage().props.auth?.user));

const steps = [
    {
        title: 'Dostaniesz maila z zaproszeniem',
        text: 'Mail przyjdzie od Studio eMCeFit. Kliknij „Aktywuj konto”. Link działa 30 dni — możesz do niego wracać. Nie widzisz maila? Zajrzyj do folderu spam lub „Oferty”.',
        img: '1-zaproszenie.png',
    },
    {
        title: 'Ustaw hasło',
        text: 'Zaznacz dwa pola pod regulaminem, wpisz hasło (co najmniej 8 znaków) i kliknij „Aktywuj konto”. Od teraz logujesz się swoim adresem e-mail i tym hasłem.',
        img: '2-aktywacja.png',
    },
    {
        title: 'Twój panel',
        text: 'Na początku widzisz najbliższe zajęcia i swój karnet. Pod każdymi zajęciami jest przycisk „Nie przyjdę — zwolnij miejsce”. Na dole ekranu masz menu: Start, Zapisy, Moje zajęcia i Pomoc.',
        img: '3-panel.png',
    },
    {
        title: 'Zapisy na kolejny miesiąc',
        text: 'Gdy studio otworzy zapisy, dostaniesz maila. Jeśli chodzisz na to samo co teraz, kliknij „Zapisz mnie na te same zajęcia”. Możesz też zaznaczyć inne zajęcia na liście.',
        img: '4-zapisy.png',
    },
    {
        title: 'Zgłoś i wpłać',
        text: 'Na dole zobaczysz cenę karnetu. Kliknij „Zgłoś” — dostaniesz maila z numerem konta. Wpłać w ciągu 3 dni (przelewem albo w studiu). Po zaksięgowaniu wpłaty miejsca są potwierdzone.',
        img: '5-zglos.png',
    },
    {
        title: 'Nie możesz przyjść?',
        text: 'Kliknij „Nie przyjdę — zwolnij miejsce” na ekranie startowym albo w „Moich zajęciach”. Jeśli zrobisz to co najmniej godzinę przed zajęciami, możesz je odrobić w tym samym miesiącu.',
        img: '6-moje-zajecia.png',
    },
    {
        title: 'Odrabianie',
        text: 'Kliknij „Odrób zajęcia” i wybierz dowolne zajęcia z wolnym miejscem. Gdy zajęcia są pełne, dopisz się do listy oczekujących — jeśli ktoś zwolni miejsce, wskoczysz automatycznie i dostaniesz maila. Zmiana planów? Zwolnij zajęcia odrabiane co najmniej godzinę przed — odrobienie wróci i wybierzesz inny termin do końca miesiąca.',
        img: '7-wolne-miejsca.png',
    },
    {
        title: 'Coś nie działa?',
        text: 'Kliknij „Pomoc” i napisz, w czym problem. Odpowiemy mailem. Formularz działa też bez logowania — link jest na stronie logowania.',
        img: '8-pomoc.png',
    },
];
</script>

<template>
    <Head title="Jak to działa" />

    <div class="min-h-screen bg-gray-100 px-4 py-8">
        <div class="mx-auto max-w-2xl">
            <div class="mb-6 flex flex-col items-center text-center">
                <Link href="/"><ApplicationLogo class="text-4xl text-gray-700" /></Link>
                <h1 class="mt-4 text-2xl font-semibold text-gray-800">Jak to działa?</h1>
                <p class="mt-1 text-sm text-gray-600">System zapisów Studio eMCeFit krok po kroku.</p>
            </div>

            <ol class="space-y-6">
                <li v-for="(step, i) in steps" :key="step.img" class="rounded-lg bg-white p-5 shadow-sm">
                    <div class="flex items-start gap-3">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-indigo-600 text-sm font-semibold text-white">
                            {{ i + 1 }}
                        </span>
                        <div>
                            <h2 class="text-lg font-semibold text-gray-800">{{ step.title }}</h2>
                            <p class="mt-1 text-sm text-gray-600">{{ step.text }}</p>
                        </div>
                    </div>
                    <img
                        :src="`/images/jak-to-dziala/${step.img}`"
                        :alt="step.title"
                        loading="lazy"
                        class="mx-auto mt-4 max-h-[560px] w-auto max-w-full rounded-lg border border-gray-200 object-contain object-top shadow-sm"
                    />
                </li>
            </ol>

            <div class="mt-6 rounded-lg bg-indigo-50 p-5 text-sm text-indigo-900">
                <h2 class="font-semibold">Wskazówka: dodaj system do ekranu telefonu</h2>
                <p class="mt-1">
                    Będziesz go mieć pod ręką jak aplikację.
                    <strong>Android (Chrome):</strong> menu ⋮ → „Dodaj do ekranu głównego”.
                    <strong>iPhone (Safari):</strong> przycisk Udostępnij → „Do ekranu początkowego”.
                </p>
            </div>

            <p class="mt-6 text-center">
                <Link
                    :href="loggedIn ? route('client.dashboard') : route('login')"
                    class="inline-block rounded-md bg-indigo-600 px-5 py-3 text-sm font-semibold text-white hover:bg-indigo-500"
                >
                    {{ loggedIn ? 'Wróć do panelu' : 'Przejdź do logowania' }}
                </Link>
            </p>
        </div>
    </div>
</template>
