<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    reason: { type: String, default: 'invalid' },
});

const message = computed(
    () =>
        ({
            expired: 'Link aktywacyjny wygasł. Poproś klub o wygenerowanie nowego.',
            used: 'Ten link został już wykorzystany — konto jest aktywne. Zaloguj się swoim hasłem.',
            invalid: 'Link aktywacyjny jest nieprawidłowy. Sprawdź, czy skopiowałeś/aś go w całości.',
        })[props.reason] ?? 'Link aktywacyjny jest nieprawidłowy.',
);
</script>

<template>
    <Head title="Link nieaktywny" />

    <div class="flex min-h-screen flex-col items-center justify-center bg-gray-100 px-4">
        <ApplicationLogo class="text-4xl text-gray-700" />
        <div class="mt-6 w-full max-w-md rounded-lg bg-white p-6 text-center shadow-sm">
            <h1 class="text-lg font-semibold text-gray-800">Nie można aktywować konta</h1>
            <p class="mt-2 text-sm text-gray-600">{{ message }}</p>
            <Link
                href="/login"
                class="mt-4 inline-flex rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500"
            >
                Przejdź do logowania
            </Link>
        </div>
    </div>
</template>
