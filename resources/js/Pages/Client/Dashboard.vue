<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    makeupCredits: { type: Number, default: 0 },
});
</script>

<template>
    <Head title="Panel" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Twój panel</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-3xl space-y-4 sm:px-6 lg:px-8">
                <div class="rounded-lg bg-white p-6 shadow-sm">
                    <p class="text-gray-800">
                        Witaj, <span class="font-semibold">{{ $page.props.auth.user.name }}</span>!
                    </p>
                    <p class="mt-2 text-sm text-gray-500">
                        Twoje konto jest aktywne. Wkrótce znajdziesz tu swój karnet, harmonogram
                        zajęć i rezerwacje.
                    </p>
                </div>

                <div
                    v-if="makeupCredits > 0"
                    class="rounded-lg border border-indigo-100 bg-indigo-50 p-6 shadow-sm"
                >
                    <p class="text-sm font-medium text-indigo-900">
                        Masz {{ makeupCredits }} zajęć do odrobienia.
                    </p>
                    <p class="mt-1 text-xs text-indigo-700">
                        Możliwość samodzielnego zapisania się na odrobienie pojawi się wkrótce.
                    </p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
