<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ClassTypeBadge from '@/Components/ClassTypeBadge.vue';
import { readableTextColor } from '@/Utils/color';
import { iconComponent } from '@/Utils/classTypeIcons';
import { Head, Link, router } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref } from 'vue';

const props = defineProps({
    month: { type: Object, required: true },
    monthOptions: { type: Array, default: () => [] },
    makeupCredits: { type: Number, default: 0 },
    membership: { type: Object, default: null },
});

const GRACE_MINUTES = 60;

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
    const question = inGrace
        ? 'Zwolnić miejsce na te zajęcia? Otrzymasz prawo do odrobienia w tym miesiącu.'
        : 'Zgodnie z regulaminem, zwolnienie miejsca później niż godzinę przed zajęciami NIE daje prawa do odrobienia. Zwolnić mimo to?';

    if (!confirm(question)) return;

    router.patch(
        route('client.reservations.cancel', res.id),
        inGrace ? {} : { acknowledge_late: true },
        { preserveScroll: true },
    );
};

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

        <div class="py-12">
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

                <div
                    v-if="makeupCredits > 0"
                    class="rounded-lg border border-indigo-100 bg-indigo-50 p-4 text-sm text-indigo-900"
                >
                    Masz {{ makeupCredits }} zajęć do odrobienia.
                </div>

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

                    <!-- Wybrane zajęcia cykliczne -->
                    <div class="rounded-lg bg-white p-5 shadow-sm">
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

                    <!-- Terminy / rezerwacje -->
                    <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="px-4 py-3">Data</th>
                                    <th class="px-4 py-3">Godz.</th>
                                    <th class="px-4 py-3">Zajęcia</th>
                                    <th class="px-4 py-3">Status</th>
                                    <th class="px-4 py-3 text-right">Akcja</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr
                                    v-for="(res, i) in membership.reservations"
                                    :key="i"
                                    :class="res.is_past ? 'text-gray-400 opacity-60' : ''"
                                >
                                    <td class="px-4 py-3 capitalize" :class="res.is_past ? '' : 'text-gray-700'">
                                        {{ res.date }}
                                    </td>
                                    <td class="px-4 py-3 tabular-nums" :class="res.is_past ? '' : 'text-gray-600'">
                                        {{ res.start_time }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center gap-2" :class="res.is_past ? 'opacity-70' : ''">
                                            <ClassTypeBadge :color="res.type_color" :icon="res.type_icon" size="sm" />
                                            {{ res.type_name }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span
                                            class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                            :class="res.is_past
                                                ? 'bg-gray-100 text-gray-500'
                                                : (reservationBadge[res.status] ?? 'bg-gray-100 text-gray-600')"
                                        >
                                            {{ res.status_label }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <button
                                            v-if="res.cancellable"
                                            type="button"
                                            class="text-xs font-medium text-red-600 hover:text-red-800"
                                            @click="cancelReservation(res)"
                                        >
                                            Zwolnij miejsce
                                        </button>
                                        <span
                                            v-else-if="res.is_past"
                                            class="text-xs text-gray-400"
                                        >
                                            zajęcia się odbyły
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="membership.reservations.length === 0">
                                    <td colspan="5" class="px-4 py-8 text-center text-gray-500">
                                        Brak terminów w tym miesiącu.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </template>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
