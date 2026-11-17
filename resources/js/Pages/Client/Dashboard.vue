<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ClassTypeBadge from '@/Components/ClassTypeBadge.vue';
import { Head, Link } from '@inertiajs/vue3';
import { useReleaseReservation } from '@/Utils/releaseReservation';

defineProps({
    firstName: { type: String, default: '' },
    upcoming: { type: Array, default: () => [] },
    current: { type: Object, default: null },
    next: { type: Object, required: true },
    makeupCredits: { type: Number, default: 0 },
    openRemaining: { type: Number, default: 0 },
});

const { isLateMakeup, releaseLabel, cancelReservation, leaveWaitlist } = useReleaseReservation();

const statusBadge = {
    potwierdzona: 'bg-green-100 text-green-800',
    oczekuje_platnosci: 'bg-amber-100 text-amber-800',
    waitlist: 'bg-blue-100 text-blue-800',
};
</script>

<template>
    <Head title="Panel" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Cześć, {{ firstName }}!</h2>
        </template>

        <div class="px-4 py-6 sm:py-10">
            <div class="mx-auto max-w-2xl space-y-4">
                <div
                    v-if="$page.props.flash?.success"
                    class="rounded-md bg-green-50 p-4 text-sm text-green-800"
                >
                    {{ $page.props.flash.success }}
                </div>
                <div
                    v-if="$page.props.flash?.error || $page.props.errors?.reservation"
                    class="rounded-md bg-red-50 p-4 text-sm text-red-800"
                >
                    {{ $page.props.flash?.error || $page.props.errors?.reservation }}
                </div>

                <!-- Zapisy na kolejny miesiąc — najważniejsza akcja, gdy są otwarte -->
                <Link
                    v-if="next.enrollment_open && !next.membership"
                    :href="route('client.enrollment.create', { month: next.value })"
                    class="block rounded-lg bg-indigo-600 p-5 text-white shadow-sm hover:bg-indigo-500"
                >
                    <div class="text-lg font-semibold">Zapisy na {{ next.label }} są otwarte</div>
                    <div class="mt-1 text-sm text-indigo-100">Kliknij, żeby wybrać swoje zajęcia →</div>
                </Link>

                <!-- Najbliższe zajęcia -->
                <section class="rounded-lg bg-white p-5 shadow-sm">
                    <h3 class="text-base font-semibold text-gray-800">Twoje najbliższe zajęcia</h3>

                    <ul v-if="upcoming.length" class="mt-3 divide-y divide-gray-100">
                        <li v-for="res in upcoming" :key="res.id" class="py-3">
                            <div class="flex items-center gap-3">
                                <ClassTypeBadge :color="res.type_color" :icon="res.type_icon" />
                                <div class="min-w-0 flex-1">
                                    <div class="font-medium text-gray-900">{{ res.date }}</div>
                                    <div class="text-sm text-gray-600">
                                        {{ res.start_time }}–{{ res.end_time }} · {{ res.type_name }}
                                    </div>
                                    <div v-if="res.makeup_for" class="text-xs text-indigo-700">
                                        odrabiasz za: {{ res.makeup_for }}
                                    </div>
                                </div>
                                <span
                                    v-if="res.status !== 'potwierdzona'"
                                    class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="statusBadge[res.status]"
                                >
                                    {{ res.status_label }}
                                </span>
                            </div>
                            <button
                                v-if="res.cancellable"
                                type="button"
                                class="mt-2 w-full rounded-md border py-2 text-sm font-medium"
                                :class="isLateMakeup(res)
                                    ? 'border-amber-300 bg-amber-50 text-amber-800 hover:bg-amber-100'
                                    : 'border-red-200 text-red-700 hover:bg-red-50'"
                                @click="cancelReservation(res)"
                            >
                                {{ releaseLabel(res) }}
                            </button>
                            <button
                                v-if="res.leavable"
                                type="button"
                                class="mt-2 w-full rounded-md border border-gray-300 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                                @click="leaveWaitlist(res)"
                            >
                                Wypisz mnie z listy oczekujących
                            </button>
                        </li>
                    </ul>
                    <p v-else class="mt-2 text-sm text-gray-500">Nie masz zaplanowanych zajęć.</p>

                    <Link
                        v-if="makeupCredits > 0 || openRemaining > 0"
                        :href="route('client.free-spots.index')"
                        class="mt-3 flex items-center justify-between gap-3 rounded-md bg-indigo-50 p-3 text-sm text-indigo-900 hover:bg-indigo-100"
                    >
                        <span v-if="makeupCredits > 0">
                            Masz {{ makeupCredits }} {{ makeupCredits === 1 ? 'zajęcia' : 'zajęć' }} do odrobienia w tym miesiącu.
                        </span>
                        <span v-else>
                            Masz {{ openRemaining }} {{ openRemaining === 1 ? 'wejście' : (openRemaining < 5 ? 'wejścia' : 'wejść') }} do wykorzystania.
                        </span>
                        <span class="shrink-0 font-semibold">
                            {{ makeupCredits > 0 ? 'Odrób zajęcia →' : 'Zapisz się →' }}
                        </span>
                    </Link>
                </section>

                <!-- Karnety -->
                <section v-if="current || next.membership" class="rounded-lg bg-white p-5 text-sm shadow-sm">
                    <h3 class="text-base font-semibold text-gray-800">Twój karnet</h3>
                    <div v-for="m in [current, next.membership].filter(Boolean)" :key="m.month_label" class="mt-2 flex items-center justify-between gap-3">
                        <span class="text-gray-700"><span class="capitalize">{{ m.month_label }}</span>: {{ m.type_name }}</span>
                        <span
                            class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium"
                            :class="m.paid ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800'"
                        >
                            {{ m.payment_label }}
                        </span>
                    </div>
                </section>

                <!-- Duże kafelki zamiast ukrytego menu -->
                <nav class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <Link
                        :href="route('client.classes.index')"
                        class="rounded-lg bg-white p-4 text-center font-semibold text-gray-800 shadow-sm ring-1 ring-gray-200 hover:bg-gray-50"
                    >
                        Moje zajęcia
                        <span class="block text-xs font-normal text-gray-500">terminy, zwalnianie miejsca</span>
                    </Link>
                    <Link
                        :href="route('client.enrollment.create', { month: next.value })"
                        class="rounded-lg bg-white p-4 text-center font-semibold text-gray-800 shadow-sm ring-1 ring-gray-200 hover:bg-gray-50"
                    >
                        Zapisy na {{ next.label }}
                        <span class="block text-xs font-normal text-gray-500">
                            {{ next.membership ? 'masz już zgłoszenie' : (next.enrollment_open ? 'otwarte' : 'jeszcze zamknięte') }}
                        </span>
                    </Link>
                    <Link
                        :href="route('help.create')"
                        class="rounded-lg bg-white p-4 text-center font-semibold text-gray-800 shadow-sm ring-1 ring-gray-200 hover:bg-gray-50"
                    >
                        Pomoc
                        <span class="block text-xs font-normal text-gray-500">coś nie działa? napisz</span>
                    </Link>
                </nav>

                <p class="text-center text-sm text-gray-500">
                    <Link :href="route('how-it-works')" class="underline">Jak to działa? Instrukcja krok po kroku</Link>
                </p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
