<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputError from '@/Components/InputError.vue';
import { readableTextColor } from '@/Utils/color';
import { iconComponent } from '@/Utils/classTypeIcons';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

const props = defineProps({
    month: { type: Object, required: true },
    monthOptions: { type: Array, default: () => [] },
    weekdays: { type: Array, default: () => [] },
    classGroups: { type: Array, default: () => [] },
    occurrencesByGroup: { type: Object, default: () => ({}) },
    scheduleGenerated: { type: Boolean, default: false },
    enrollmentOpen: { type: Boolean, default: false },
    alreadyEnrolled: { type: Boolean, default: false },
    pricing: { type: Array, default: () => [] },
    ctx: {
        type: Object,
        default: () => ({
            admin_mode: false,
            client_name: null,
            create_route: 'client.enrollment.create',
            store_route: 'client.enrollment.store',
            submission_route: 'client.classes.index',
            route_params: {},
        }),
    },
});

// Parametry trasy wspólne dla obu ścieżek (klient / admin w imieniu klienta, Prompt 17).
const routeArgs = (extra = {}) => ({ ...props.ctx.route_params, ...extra });
const submissionHref = () =>
    props.ctx.admin_mode
        ? route(props.ctx.submission_route, routeArgs())
        : route(props.ctx.submission_route, { month: props.month.value });

const money = (value) =>
    new Intl.NumberFormat('pl-PL', { style: 'currency', currency: 'PLN' }).format(Number(value));

const selected = reactive(new Set());
// { [class_schedule_id]: true }  — domyślnie "będę"
const attendance = reactive({});

const occurrencesFor = (groupId) => props.occurrencesByGroup[groupId] ?? [];
// Wystąpienie, które klient może jeszcze wybrać (nie odwołane przez klub, nie minione — Prompt 10h).
const selectable = (occ) => !occ.cancelled && !occ.past;

const toggleGroup = (groupId) => {
    if (selected.has(groupId)) {
        selected.delete(groupId);
    } else {
        selected.add(groupId);
        for (const occ of occurrencesFor(groupId)) {
            if (selectable(occ) && attendance[occ.id] === undefined) attendance[occ.id] = true;
        }
    }
};

const count = computed(() => selected.size);

const columns = computed(() =>
    props.weekdays.map((day) => ({
        ...day,
        items: props.classGroups
            .filter((g) => g.weekday === day.value)
            .sort((a, b) => a.start_time.localeCompare(b.start_time)),
    })),
);

const maxVariant = computed(() =>
    props.pricing.reduce(
        (max, p) =>
            p.validity_type === 'miesiac_kalendarzowy' &&
            p.sessions_per_week > (max?.sessions_per_week ?? 0)
                ? p
                : max,
        null,
    ),
);

// Poniedziałek tygodnia ISO, do którego należy dana data (bez pułapek strefy czasowej).
const weekStart = (isoDate) => {
    const [y, m, d] = isoDate.split('-').map(Number);
    const dt = new Date(y, m - 1, d);
    dt.setDate(dt.getDate() - ((dt.getDay() + 6) % 7));
    const mm = String(dt.getMonth() + 1).padStart(2, '0');
    const dd = String(dt.getDate()).padStart(2, '0');
    return `${dt.getFullYear()}-${mm}-${dd}`;
};

// Tygodnie z zajęciami vs tygodnie z obecnością (Prompt 10e). Tygodnie, w których
// wszystkie wystąpienia odwołał klub albo już minęły (Prompt 10h), nie liczą się.
const weekStats = computed(() => {
    const weeks = {};
    for (const groupId of selected) {
        for (const occ of occurrencesFor(groupId)) {
            if (!selectable(occ)) continue;
            const key = weekStart(occ.date);
            (weeks[key] ??= { live: false, attend: false });
            weeks[key].live = true;
            if (attendance[occ.id] !== false) weeks[key].attend = true;
        }
    }
    let total = 0;
    let attend = 0;
    for (const key in weeks) {
        if (weeks[key].live) total += 1;
        if (weeks[key].attend) attend += 1;
    }
    return { total, attend };
});

const weeksLabel = (n) => `${n} ${n === 1 ? 'tydzień' : n >= 2 && n <= 4 ? 'tygodnie' : 'tygodni'}`;

const priceInfo = computed(() => {
    if (count.value === 0) return { state: 'empty' };

    const monthly = props.pricing.find(
        (p) => p.validity_type === 'miesiac_kalendarzowy' && p.sessions_per_week === count.value,
    );
    const { total, attend } = weekStats.value;

    // Pełny miesiąc obecności (albo brak zaznaczonych "będę") => wariant miesięczny.
    if (attend === 0 || attend >= total) {
        return monthly
            ? { state: 'ok', name: monthly.name, price: monthly.price, per: '/ miesiąc' }
            : { state: 'no_variant' };
    }

    const shorter = props.pricing.find(
        (p) =>
            p.validity_type === 'tygodnie_od_pierwszego_wejscia' &&
            p.sessions_per_week === count.value &&
            p.validity_value === attend,
    );
    if (shorter) {
        return {
            state: 'ok',
            name: shorter.name,
            price: shorter.price,
            per: `/ ${weeksLabel(attend)}`,
            shortened: true,
        };
    }

    return monthly
        ? { state: 'fallback', name: monthly.name, price: monthly.price, per: '/ miesiąc' }
        : { state: 'no_variant' };
});

// Prompt 21: dla skróconego wariantu (Prompt 10e) karnet obejmuje tylko okres
// [pierwsze "będę", ostatnie "będę"] — jak `whereBetween('date', ...)` na serwerze.
// Daty poza tym oknem (tygodnie, które spowodowały skrócenie) nie dają ani rezerwacji,
// ani odrobienia — więc nie liczymy ich też w podglądzie.
const paidDateBounds = computed(() => {
    if (!priceInfo.value.shortened) return null;
    let first = null;
    let last = null;
    for (const groupId of selected) {
        for (const occ of occurrencesFor(groupId)) {
            if (!selectable(occ) || attendance[occ.id] === false) continue;
            if (first === null || occ.date < first) first = occ.date;
            if (last === null || occ.date > last) last = occ.date;
        }
    }
    return first === null ? null : { first, last };
});

const inPaidPeriod = (occ) => {
    const b = paidDateBounds.value;
    return !b || (occ.date >= b.first && occ.date <= b.last);
};

// class_schedule_id, na które klient zadeklarował nieobecność (w obrębie okresu karnetu)
const absences = computed(() => {
    const ids = [];
    for (const groupId of selected) {
        for (const occ of occurrencesFor(groupId)) {
            if (selectable(occ) && inPaidPeriod(occ) && attendance[occ.id] === false) ids.push(occ.id);
        }
    }
    return ids;
});

const attendingCount = computed(() => {
    let n = 0;
    for (const groupId of selected) {
        for (const occ of occurrencesFor(groupId)) {
            if (selectable(occ) && inPaidPeriod(occ) && attendance[occ.id] !== false) n++;
        }
    }
    return n;
});

// wystąpienia wybranych zajęć odwołane z góry przez klub (w obrębie okresu karnetu)
const clubCancelledCount = computed(() => {
    let n = 0;
    for (const groupId of selected) {
        for (const occ of occurrencesFor(groupId)) {
            if (occ.cancelled && inPaidPeriod(occ)) n++;
        }
    }
    return n;
});

// łączna liczba zajęć, za które klient dostanie prawo do odrobienia
const makeupCount = computed(() => absences.value.length + clubCancelledCount.value);

const changeMonth = (value) => {
    if (value === props.month.value) return;
    selected.clear();
    router.get(route(props.ctx.create_route, routeArgs({ month: value })), {}, { preserveScroll: true });
};

const form = useForm({ month: props.month.value, class_group_ids: [], absences: [] });

const canSubmit = computed(
    () =>
        props.enrollmentOpen &&
        props.scheduleGenerated &&
        (priceInfo.value.state === 'ok' || priceInfo.value.state === 'fallback') &&
        !form.processing,
);

const hasSubmission = computed(
    () =>
        props.alreadyEnrolled ||
        (form.errors.class_group_ids ?? '').includes('zgłoszenie na ten miesiąc'),
);

const submit = () => {
    form.month = props.month.value;
    form.class_group_ids = [...selected];
    form.absences = absences.value;
    form.post(route(props.ctx.store_route, routeArgs()));
};
</script>

<template>
    <Head title="Zapisy na zajęcia" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                {{ ctx.admin_mode ? `Zapis na zajęcia — ${ctx.client_name}` : 'Zapisz się na zajęcia' }}
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-6xl space-y-4 sm:px-6 lg:px-8">
                <div
                    v-if="ctx.admin_mode"
                    class="flex flex-wrap items-center justify-between gap-2 rounded-md bg-amber-50 p-3 text-sm text-amber-800"
                >
                    <span>Zapisujesz klienta <strong>{{ ctx.client_name }}</strong> w jego imieniu.</span>
                    <Link :href="submissionHref()" class="font-medium text-amber-900 underline">
                        Wróć do karty klienta
                    </Link>
                </div>

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

                <!-- Klient ma już zgłoszenie na ten miesiąc — nie pokazujemy formularza -->
                <div
                    v-if="hasSubmission"
                    class="rounded-lg border border-indigo-100 bg-indigo-50 p-4 text-sm text-indigo-900"
                >
                    {{ ctx.admin_mode ? 'Ten klient ma już' : 'Masz już' }} zgłoszenie na
                    <span class="capitalize">{{ month.label }}</span>.
                    <Link
                        :href="submissionHref()"
                        class="ml-1 font-medium text-indigo-700 underline hover:text-indigo-900"
                    >
                        {{ ctx.admin_mode ? 'Wróć do karty klienta' : 'Zobacz szczegóły swojego zgłoszenia' }} →
                    </Link>
                </div>

                <template v-else>
                    <p
                        v-if="!enrollmentOpen"
                        class="rounded-md bg-amber-50 p-4 text-sm text-amber-800"
                    >
                        Zapisy na <span class="capitalize">{{ month.label }}</span> nie zostały
                        jeszcze otwarte przez klub — sprawdź później.
                    </p>
                    <p
                        v-else-if="!scheduleGenerated"
                        class="rounded-md bg-amber-50 p-3 text-sm text-amber-800"
                    >
                        Harmonogram na <span class="capitalize">{{ month.label }}</span> nie został
                        jeszcze wygenerowany przez klub — zgłoszenie będzie możliwe później.
                    </p>

                    <InputError :message="form.errors.class_group_ids" class="text-sm" />

                    <div v-if="enrollmentOpen" class="grid gap-4 lg:grid-cols-[1fr_18rem]">
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                        <div v-for="col in columns" :key="col.value" class="rounded-lg bg-white shadow-sm">
                            <div class="border-b border-gray-100 px-3 py-2 text-sm font-semibold text-gray-700">
                                {{ col.label }}
                            </div>
                            <div class="space-y-2 p-3">
                                <div
                                    v-for="item in col.items"
                                    :key="item.id"
                                    class="rounded-md border p-2 text-xs"
                                    :class="selected.has(item.id) ? 'border-indigo-400 bg-indigo-50' : 'border-gray-200'"
                                >
                                    <label class="flex cursor-pointer gap-2">
                                        <input
                                            type="checkbox"
                                            class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                            :checked="selected.has(item.id)"
                                            @change="toggleGroup(item.id)"
                                        />
                                        <span>
                                            <span class="font-semibold text-gray-800">
                                                {{ item.start_time }}–{{ item.end_time }}
                                            </span>
                                            <span
                                                class="ml-1 inline-flex items-center gap-1 rounded px-1 py-0.5"
                                                :style="{
                                                    backgroundColor: item.type_color,
                                                    color: readableTextColor(item.type_color),
                                                }"
                                            >
                                                <component :is="iconComponent(item.type_icon)" :size="11" :stroke-width="2.5" />
                                                {{ item.type_name }}
                                            </span>
                                            <span
                                                class="mt-0.5 block"
                                                :class="item.free_spots <= 0 ? 'font-semibold text-red-600' : 'text-gray-500'"
                                            >
                                                {{ item.free_spots <= 0 ? 'Brak wolnych miejsc' : `${item.free_spots} wolnych miejsc` }}
                                            </span>
                                        </span>
                                    </label>

                                    <!-- Daty tego miesiąca -->
                                    <div v-if="selected.has(item.id)" class="mt-2 space-y-1 border-t border-indigo-100 pt-2">
                                        <template v-for="occ in occurrencesFor(item.id)" :key="occ.id">
                                            <div
                                                v-if="occ.cancelled"
                                                class="text-gray-400 line-through"
                                                :title="occ.cancellation_reason || ''"
                                            >
                                                {{ occ.label }} · odwołane przez klub
                                            </div>
                                            <div v-else-if="occ.past" class="text-gray-400">
                                                {{ occ.label }} · już minęło
                                            </div>
                                            <label v-else class="flex items-center gap-2">
                                                <input
                                                    type="checkbox"
                                                    class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                                    :checked="attendance[occ.id] !== false"
                                                    @change="attendance[occ.id] = $event.target.checked"
                                                />
                                                <span :class="attendance[occ.id] === false ? 'text-gray-400 line-through' : ''">
                                                    {{ occ.label }}
                                                    <span v-if="attendance[occ.id] === false"> · nie będę</span>
                                                </span>
                                            </label>
                                        </template>
                                        <p v-if="occurrencesFor(item.id).length === 0" class="text-gray-400">
                                            brak terminów w tym miesiącu
                                        </p>
                                    </div>
                                </div>

                                <p v-if="col.items.length === 0" class="py-4 text-center text-xs text-gray-400">
                                    brak zajęć
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="h-fit rounded-lg bg-white p-5 shadow-sm lg:sticky lg:top-6">
                        <div class="text-sm text-gray-500">Wybrane zajęcia / tydzień</div>
                        <div class="mt-1 text-3xl font-semibold text-gray-900">{{ count }}</div>

                        <div class="mt-4 border-t border-gray-100 pt-4">
                            <template v-if="priceInfo.state === 'empty'">
                                <p class="text-sm text-gray-500">Zaznacz zajęcia, aby zobaczyć cenę karnetu.</p>
                            </template>
                            <template v-else-if="priceInfo.state === 'ok' || priceInfo.state === 'fallback'">
                                <div class="text-sm text-gray-500">{{ priceInfo.name }}</div>
                                <div class="mt-1 text-2xl font-semibold text-gray-900">
                                    {{ money(priceInfo.price) }}
                                    <span class="text-sm font-normal text-gray-400">{{ priceInfo.per }}</span>
                                </div>
                                <p v-if="priceInfo.shortened" class="mt-1 text-xs text-emerald-700">
                                    Pominięte całe tygodnie — dobrano krótszy pakiet.
                                </p>
                                <p v-else-if="priceInfo.state === 'fallback'" class="mt-1 text-xs text-amber-700">
                                    Wybrany wzorzec obecności nie pasuje do żadnego krótszego pakietu —
                                    zastosowano cenę pełnego miesiąca.
                                </p>
                            </template>
                            <template v-else>
                                <p class="text-sm text-amber-700">
                                    Cennik nie przewiduje wariantu na {{ count }} zajęć w tygodniu.
                                    <template v-if="maxVariant">
                                        Maksymalnie {{ maxVariant.sessions_per_week }}
                                        ({{ money(maxVariant.price) }}). Odznacz część zajęć.
                                    </template>
                                </p>
                            </template>
                        </div>

                        <div
                            v-if="makeupCount > 0"
                            class="mt-3 flex items-center gap-1.5 rounded-lg bg-amber-100 px-3 py-2 text-sm font-semibold text-amber-800"
                        >
                            <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path
                                    fill-rule="evenodd"
                                    d="M15.312 11.424a5.5 5.5 0 01-9.201 2.466l-.312-.311h2.433a.75.75 0 000-1.5H3.989a.75.75 0 00-.75.75v4.242a.75.75 0 001.5 0v-2.43l.31.31a7 7 0 0011.712-3.138.75.75 0 00-1.449-.39zm1.23-3.723a.75.75 0 00.219-.53V2.929a.75.75 0 00-1.5 0V5.36l-.31-.31A7 7 0 003.239 8.188a.75.75 0 101.448.389A5.5 5.5 0 0113.89 6.11l.311.311h-2.432a.75.75 0 000 1.5h4.243a.75.75 0 00.53-.219z"
                                    clip-rule="evenodd"
                                />
                            </svg>
                            +{{ makeupCount }} zajęć do odrobienia
                        </div>

                        <div v-if="count > 0 && scheduleGenerated" class="mt-3 text-xs text-gray-500">
                            Terminy: {{ attendingCount }} „będę", {{ absences.length }} „nie będę"<span
                                v-if="clubCancelledCount > 0"
                            >, {{ clubCancelledCount }} odwołane przez klub</span>. Za każdą taką pozycję
                            dostajesz jedno zajęcie do odrobienia w tym miesiącu.
                        </div>

                        <PrimaryButton class="mt-4 w-full justify-center" :disabled="!canSubmit" @click="submit">
                            Zgłoś chęć udziału
                        </PrimaryButton>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
