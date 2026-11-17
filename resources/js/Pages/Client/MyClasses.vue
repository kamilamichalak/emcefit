<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ClassTypeBadge from '@/Components/ClassTypeBadge.vue';
import { readableTextColor } from '@/Utils/color';
import { iconComponent } from '@/Utils/classTypeIcons';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';

const props = defineProps({
    month: { type: Object, required: true },
    monthOptions: { type: Array, default: () => [] },
    makeupCredits: { type: Number, default: 0 },
    openRemaining: { type: Number, default: 0 },
    membership: { type: Object, default: null },
});

const GRACE_MINUTES = 60;

// Minione terminy domyślnie schowane — na ekranie zostaje to, co przed klientką.
const showPast = ref(false);
const pastReservations = computed(() => (props.membership?.reservations ?? []).filter((r) => r.is_past));
const visibleReservations = computed(() =>
    (props.membership?.reservations ?? []).filter((r) => showPast.value || !r.is_past),
);

const money = (value) =>
    new Intl.NumberFormat('pl-PL', { style: 'currency', currency: 'PLN' }).format(Number(value));

const changeMonth = (value) => {
    if (value === props.month.value) return;
    router.get(route('client.classes.index', { month: value }), {}, { preserveScroll: true });
};

// „Teraz" odświeżane co minutę — żeby okno łaski (1h) było aktualne bez przeładowania.
const now = ref(Date.now());
let ticker = null;
onMounted(() => {
    ticker = setInterval(() => (now.value = Date.now()), 60_000);
});
onUnmounted(() => clearInterval(ticker));

const withinGracePeriod = (res) =>
    new Date(res.starts_at).getTime() - now.value >= GRACE_MINUTES * 60_000;

const cancelReservation = (res) => {
    const inGrace = withinGracePeriod(res);
    let question;
    let loses;

    if (res.kind === 'odrobienie') {
        question =
            'UWAGA: to są zajęcia ODRABIANE' + (res.makeup_for ? ` (za ${res.makeup_for})` : '') + '.\n\n' +
            'Jeśli zwolnisz to miejsce, odrobienie przepadnie i nie odrobisz już tamtych zajęć.\n\n' +
            'Na pewno zwolnić?';
        loses = true;
    } else if (res.kind === 'otwarty') {
        question = inGrace
            ? 'Zwolnić miejsce? Wejście wróci na Twój karnet.'
            : 'Do zajęć została mniej niż godzina — po zwolnieniu wejście przepada. Zwolnić mimo to?';
        loses = !inGrace;
    } else {
        question = inGrace
            ? 'Zwolnić miejsce na te zajęcia? Otrzymasz prawo do odrobienia w tym miesiącu.'
            : 'Zgodnie z regulaminem, zwolnienie miejsca później niż godzinę przed zajęciami NIE daje prawa do odrobienia. Zwolnić mimo to?';
        loses = !inGrace;
    }

    if (!confirm(question)) return;

    router.patch(
        route('client.reservations.cancel', res.id),
        loses ? { acknowledge_late: true } : {},
        { preserveScroll: true },
    );
};

const leaveWaitlist = (res) => {
    if (!confirm('Wypisać Cię z listy oczekujących na te zajęcia?')) return;
    router.patch(route('client.reservations.cancel', res.id), {}, { preserveScroll: true });
};

const kindLabel = { odrobienie: 'odrabiasz', otwarty: 'z karnetu otwartego' };

const paymentBadge = {
    zaksiegowana: 'bg-green-100 text-green-800',
    oczekuje: 'bg-amber-100 text-amber-800',
    brak: 'bg-gray-100 text-gray-600',
};

const reservationBadge = {
    potwierdzona: 'bg-green-100 text-green-800',
    oczekuje_platnosci: 'bg-amber-100 text-amber-800',
    waitlist: 'bg-blue-100 text-blue-800',
    zwolnione: 'bg-red-100 text-red-700',
    odrobiona: 'bg-indigo-100 text-indigo-800',
};
</script>

<template>
    <Head title="Moje zajęcia" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Moje zajęcia</h2>
        </template>

        <div class="px-4 py-6 sm:px-0 sm:py-12">
            <div class="mx-auto max-w-4xl space-y-4 sm:px-6 lg:px-8">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-sm text-gray-500">Miesiąc:</span>
                    <button
                        v-for="opt in monthOptions"
                        :key="opt.value"
                        type="button"
                        class="rounded-md border px-3 py-1.5 text-sm capitalize"
                        :class="
                            opt.value === month.value
                                ? 'border-indigo-500 bg-indigo-50 font-semibold text-indigo-700'
                                : 'border-gray-300 text-gray-700 hover:bg-gray-50'
                        "
                        @click="changeMonth(opt.value)"
                    >
                        {{ opt.label }}
                    </button>
                </div>

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

                <Link
                    v-if="makeupCredits > 0 || openRemaining > 0"
                    :href="route('client.free-spots.index')"
                    class="flex items-center justify-between gap-3 rounded-lg border border-indigo-100 bg-indigo-50 p-4 text-sm text-indigo-900 hover:bg-indigo-100"
                >
                    <span v-if="makeupCredits > 0">
                        Masz {{ makeupCredits }} {{ makeupCredits === 1 ? 'zajęcia' : 'zajęć' }} do odrobienia.
                    </span>
                    <span v-else>Masz {{ openRemaining }} {{ openRemaining === 1 ? 'wejście' : (openRemaining < 5 ? 'wejścia' : 'wejść') }} do wykorzystania.</span>
                    <span class="shrink-0 font-semibold">{{ makeupCredits > 0 ? 'Odrób zajęcia →' : 'Zapisz się →' }}</span>
                </Link>

                <!-- Brak zgłoszenia -->
                <div
                    v-if="!membership"
                    class="rounded-lg bg-white p-6 text-center shadow-sm"
                >
                    <p class="text-sm text-gray-600">
                        Nie masz jeszcze zgłoszenia na
                        <span class="capitalize">{{ month.label }}</span>.
                    </p>
                    <Link
                        :href="route('client.enrollment.create', { month: month.value })"
                        class="mt-3 inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500"
                    >
                        Zapisz się na zajęcia
                    </Link>
                </div>

                <template v-else>
                    <!-- Karnet + płatność -->
                    <div class="rounded-lg bg-white p-5 shadow-sm">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <div class="text-sm text-gray-500">Karnet</div>
                                <div class="text-lg font-semibold text-gray-900">
                                    {{ membership.type_name }}
                                </div>
                                <div class="mt-0.5 text-sm text-gray-500">
                                    {{ money(membership.price) }} / miesiąc ·
                                    {{ membership.start_date }}<template v-if="membership.end_date"> – {{ membership.end_date }}</template>
                                </div>
                            </div>
                            <span
                                class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                :class="paymentBadge[membership.payment_status]"
                            >
                                {{ membership.payment_status_label }}
                            </span>
                        </div>
                    </div>

                    <!-- Wybrane zajęcia cykliczne (tylko karnet zamknięty) -->
                    <div v-if="membership.classes.length" class="rounded-lg bg-white p-5 shadow-sm">
                        <h3 class="text-sm font-semibold text-gray-700">Wybrane zajęcia (co tydzień)</h3>
                        <ul class="mt-3 space-y-2">
                            <li
                                v-for="(cls, i) in membership.classes"
                                :key="i"
                                class="flex items-center gap-2 text-sm text-gray-700"
                            >
                                <span class="w-24 font-medium">{{ cls.weekday_label }}</span>
                                <span class="tabular-nums">{{ cls.start_time }}–{{ cls.end_time }}</span>
                                <span
                                    class="inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-xs"
                                    :style="{
                                        backgroundColor: cls.type_color,
                                        color: readableTextColor(cls.type_color),
                                    }"
                                >
                                    <component :is="iconComponent(cls.type_icon)" :size="12" :stroke-width="2.5" />
                                    {{ cls.type_name }}
                                </span>
                            </li>
                        </ul>
                    </div>

                    <!-- Terminy / rezerwacje — kafelki (czytelne na telefonie) -->
                    <section class="rounded-lg bg-white shadow-sm">
                        <h3 class="px-5 pt-4 text-sm font-semibold text-gray-700">Terminy</h3>

                        <p v-if="membership.reservations.length === 0" class="px-5 py-8 text-center text-sm text-gray-500">
                            Brak terminów w tym miesiącu.
                        </p>

                        <button
                            v-if="pastReservations.length"
                            type="button"
                            class="mx-5 mt-2 text-xs text-gray-500 underline"
                            @click="showPast = !showPast"
                        >
                            {{ showPast ? 'Ukryj minione' : `Pokaż minione (${pastReservations.length})` }}
                        </button>

                        <ul class="mt-2 divide-y divide-gray-100">
                            <li
                                v-for="res in visibleReservations"
                                :key="res.id"
                                class="px-5 py-3"
                                :class="res.is_past ? 'opacity-50' : ''"
                            >
                                <div class="flex items-center gap-3">
                                    <ClassTypeBadge :color="res.type_color" :icon="res.type_icon" />
                                    <div class="min-w-0 flex-1">
                                        <div class="font-medium text-gray-900">{{ res.date }}</div>
                                        <div class="text-sm text-gray-600">
                                            {{ res.start_time }} · {{ res.type_name }}
                                            <span v-if="kindLabel[res.kind] && !res.makeup_for && res.status !== 'zwolnione'" class="text-indigo-700">· {{ kindLabel[res.kind] }}</span>
                                        </div>
                                        <div v-if="res.makeup_for && res.status !== 'zwolnione'" class="text-xs text-indigo-700">odrabiasz za: {{ res.makeup_for }}</div>
                                        <div v-if="res.release_note" class="text-xs text-gray-500">{{ res.release_note }}</div>
                                    </div>
                                    <span
                                        class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="res.is_past
                                            ? 'bg-gray-100 text-gray-500'
                                            : (reservationBadge[res.status] ?? 'bg-gray-100 text-gray-600')"
                                    >
                                        {{ res.is_past && res.status === 'potwierdzona' ? 'odbyte' : res.status_label }}
                                    </span>
                                </div>
                                <button
                                    v-if="res.cancellable"
                                    type="button"
                                    class="mt-2 w-full rounded-md border py-2 text-sm font-medium"
                                    :class="res.kind === 'odrobienie'
                                        ? 'border-amber-300 bg-amber-50 text-amber-800 hover:bg-amber-100'
                                        : 'border-red-200 text-red-700 hover:bg-red-50'"
                                    @click="cancelReservation(res)"
                                >
                                    {{ res.kind === 'odrobienie' ? 'Rezygnuję z odrabiania — odrobienie przepadnie' : 'Nie przyjdę — zwolnij miejsce' }}
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
                    </section>
                </template>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
