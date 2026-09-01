<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { readableTextColor } from '@/Utils/color';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    monthLabel: { type: String, required: true },
    membershipTypeName: { type: String, required: true },
    price: { type: [Number, String], required: true },
    classes: { type: Array, default: () => [] },
    pendingCount: { type: Number, default: 0 },
    makeupCount: { type: Number, default: 0 },
    bank: { type: Object, required: true },
});

const money = (value) =>
    new Intl.NumberFormat('pl-PL', { style: 'currency', currency: 'PLN' }).format(Number(value));
</script>

<template>
    <Head title="Zgłoszenie przyjęte" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Zgłoszenie przyjęte</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-2xl space-y-4 sm:px-6 lg:px-8">
                <div
                    v-if="$page.props.flash?.success"
                    class="rounded-md bg-green-50 p-4 text-sm text-green-800"
                >
                    {{ $page.props.flash.success }}
                </div>

                <div class="space-y-4 rounded-lg bg-white p-6 shadow-sm">
                    <div>
                        <div class="text-sm text-gray-500">Karnet na <span class="capitalize">{{ monthLabel }}</span></div>
                        <div class="text-lg font-semibold text-gray-900">{{ membershipTypeName }}</div>
                        <div class="text-2xl font-semibold text-gray-900">
                            {{ money(price) }} <span class="text-sm font-normal text-gray-400">/ miesiąc</span>
                        </div>
                    </div>

                    <div class="border-t border-gray-100 pt-4">
                        <div class="text-sm font-medium text-gray-700">Twój plan tygodniowy</div>
                        <ul class="mt-2 space-y-1 text-sm">
                            <li v-for="(c, i) in classes" :key="i" class="flex items-center gap-2">
                                <span
                                    class="inline-flex rounded px-1 py-0.5 text-xs"
                                    :style="{ backgroundColor: c.type_color, color: readableTextColor(c.type_color) }"
                                >
                                    {{ c.type_name }}
                                </span>
                                <span class="text-gray-700">
                                    {{ c.weekday_label }}, {{ c.start_time }}–{{ c.end_time }}
                                </span>
                            </li>
                        </ul>
                        <p class="mt-2 text-xs text-gray-500">
                            {{ pendingCount }} zajęć do opłacenia w tym miesiącu.
                            <template v-if="makeupCount > 0">
                                {{ makeupCount }} do odrobienia (nieobecności / odwołane przez klub).
                            </template>
                        </p>
                    </div>

                    <div class="rounded-md bg-gray-50 p-4 text-sm">
                        <div class="font-medium text-gray-700">Dane do przelewu</div>
                        <div class="mt-1 text-gray-600">
                            Numer konta: <span class="font-mono">{{ bank.account }}</span>
                        </div>
                        <div class="text-gray-600">Tytuł: {{ bank.title }}</div>
                        <p class="mt-2 text-xs text-gray-500">
                            Rezerwacje zostaną potwierdzone po zaksięgowaniu wpłaty przez klub.
                            Opłać do 28. dnia miesiąca poprzedzającego.
                        </p>
                    </div>
                </div>

                <Link
                    :href="route('client.dashboard')"
                    class="inline-flex rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500"
                >
                    Wróć do panelu
                </Link>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
