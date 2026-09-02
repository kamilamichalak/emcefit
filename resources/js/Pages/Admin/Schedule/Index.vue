<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { readableTextColor } from '@/Utils/color';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    month: { type: Object, required: true },
    occurrences: { type: Array, default: () => [] },
    generated: { type: Boolean, default: false },
    patternCount: { type: Number, default: 0 },
    enrollmentOpen: { type: Boolean, default: false },
});

const dayNames = ['Pon', 'Wt', 'Śr', 'Czw', 'Pt', 'Sob', 'Ndz'];

const byDate = computed(() => {
    const map = {};
    for (const o of props.occurrences) {
        (map[o.date] ??= []).push(o);
    }
    return map;
});

// Siatka kalendarza: puste komórki na początek + dni miesiąca.
const cells = computed(() => {
    const [y, m] = props.month.value.split('-').map(Number);
    const first = new Date(y, m - 1, 1);
    const daysInMonth = new Date(y, m, 0).getDate();
    const leading = (first.getDay() + 6) % 7; // pon = 0

    const result = [];
    for (let i = 0; i < leading; i++) result.push(null);
    for (let d = 1; d <= daysInMonth; d++) {
        const date = `${props.month.value}-${String(d).padStart(2, '0')}`;
        result.push({ day: d, date, items: byDate.value[date] ?? [] });
    }
    while (result.length % 7 !== 0) result.push(null);
    return result;
});

const selectedDate = ref(null);
const selectedItems = computed(() =>
    selectedDate.value ? byDate.value[selectedDate.value] ?? [] : [],
);
const expandedId = ref(null);
const toggleRoster = (id) => {
    expandedId.value = expandedId.value === id ? null : id;
};

const reservationBadge = {
    potwierdzona: 'bg-green-100 text-green-800',
    waitlist: 'bg-blue-100 text-blue-800',
    oczekuje_platnosci: 'bg-amber-100 text-amber-800',
    zwolnione: 'bg-red-100 text-red-700',
    odrobiona: 'bg-indigo-100 text-indigo-800',
};

const goToMonth = (value) => {
    selectedDate.value = null;
    expandedId.value = null;
    router.get(route('admin.schedule.index', { month: value }), {}, { preserveScroll: true });
};

const generateForm = useForm({ month: props.month.value, regenerate: false });

const generate = () => {
    generateForm.regenerate = false;
    generateForm.month = props.month.value;
    generateForm.post(route('admin.schedule.generate'), { preserveScroll: true });
};

const regenerate = () => {
    if (!confirm('Zregenerować harmonogram z aktualnego wzorca? Planowane zajęcia zostaną odbudowane; odwołane pozostaną bez zmian.')) {
        return;
    }
    generateForm.regenerate = true;
    generateForm.month = props.month.value;
    generateForm.post(route('admin.schedule.generate'), { preserveScroll: true });
};

const toggleEnrollment = () => {
    const opening = !props.enrollmentOpen;
    const q = opening
        ? `Otworzyć zapisy klientów na ${props.month.label}? Klienci będą mogli się zapisywać.`
        : `Zamknąć zapisy klientów na ${props.month.label}?`;
    if (!confirm(q)) return;
    router.patch(
        route('admin.schedule.enrollment'),
        { month: props.month.value, open: opening },
        { preserveScroll: true },
    );
};

const cancelOccurrence = (item) => {
    const reason = window.prompt(`Powód odwołania zajęć „${item.type_name}” (${selectedDate.value}, ${item.start_time}):`);
    if (reason === null) return;
    if (!reason.trim()) {
        alert('Powód jest wymagany.');
        return;
    }
    router.patch(
        route('admin.schedule.occurrences.cancel', item.id),
        { reason: reason.trim() },
        { preserveScroll: true },
    );
};

const restoreOccurrence = (item) => {
    if (!confirm('Przywrócić te zajęcia jako planowane?')) return;
    router.patch(route('admin.schedule.occurrences.restore', item.id), {}, { preserveScroll: true });
};
</script>

<template>
    <Head title="Harmonogram" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Harmonogram miesięczny</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-6xl space-y-4 sm:px-6 lg:px-8">
                <div v-if="$page.props.flash?.success" class="rounded-md bg-green-50 p-4 text-sm text-green-800">
                    {{ $page.props.flash.success }}
                </div>
                <div v-if="$page.props.flash?.warning" class="rounded-md bg-amber-50 p-4 text-sm text-amber-800">
                    {{ $page.props.flash.warning }}
                </div>
                <div v-if="$page.props.flash?.error" class="rounded-md bg-red-50 p-4 text-sm text-red-800">
                    {{ $page.props.flash.error }}
                </div>

                <!-- Pasek: miesiąc + generowanie -->
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <button
                            type="button"
                            class="rounded-md border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50"
                            @click="goToMonth(month.prev)"
                        >
                            ‹
                        </button>
                        <span class="w-44 text-center text-sm font-semibold capitalize text-gray-800">
                            {{ month.label }}
                        </span>
                        <button
                            type="button"
                            class="rounded-md border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50"
                            @click="goToMonth(month.next)"
                        >
                            ›
                        </button>
                    </div>

                    <div class="flex items-center gap-2">
                        <button
                            v-if="!generated"
                            type="button"
                            :disabled="generateForm.processing || patternCount === 0"
                            class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500 disabled:opacity-50"
                            @click="generate"
                        >
                            Wygeneruj harmonogram na <span class="capitalize">{{ month.label }}</span>
                        </button>
                        <template v-else>
                            <span class="text-sm text-gray-500">{{ occurrences.length }} zajęć w tym miesiącu</span>
                            <button
                                type="button"
                                :disabled="generateForm.processing"
                                class="rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                                @click="regenerate"
                            >
                                Regeneruj z wzorca
                            </button>
                        </template>
                    </div>
                </div>

                <p v-if="patternCount === 0" class="rounded-md bg-amber-50 p-3 text-sm text-amber-800">
                    Brak wzorca tygodniowego dla tego miesiąca — ułóż go najpierw w zakładce „Grafik".
                </p>

                <!-- Zapisy klientów na ten miesiąc -->
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg bg-white p-4 text-sm shadow-sm">
                    <div>
                        <span class="font-medium text-gray-700">Zapisy klientów na
                            <span class="capitalize">{{ month.label }}</span>:</span>
                        <span
                            class="ml-1 inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                            :class="enrollmentOpen ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'"
                        >
                            {{ enrollmentOpen ? 'otwarte' : 'zamknięte' }}
                        </span>
                        <p class="mt-0.5 text-xs text-gray-400">
                            Wygenerowanie harmonogramu nie otwiera zapisów — to osobna decyzja.
                        </p>
                    </div>
                    <button
                        type="button"
                        class="rounded-md border px-3 py-2 text-sm font-medium"
                        :class="
                            enrollmentOpen
                                ? 'border-gray-300 text-gray-700 hover:bg-gray-50'
                                : 'border-transparent bg-indigo-600 text-white hover:bg-indigo-500'
                        "
                        @click="toggleEnrollment"
                    >
                        {{ enrollmentOpen ? 'Zamknij zapisy' : 'Otwórz zapisy' }}
                    </button>
                </div>

                <!-- Kalendarz -->
                <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                    <div class="grid grid-cols-7 border-b border-gray-100 bg-gray-50 text-center text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <div v-for="name in dayNames" :key="name" class="px-2 py-2">{{ name }}</div>
                    </div>
                    <div class="grid grid-cols-7">
                        <div
                            v-for="(cell, i) in cells"
                            :key="i"
                            class="min-h-[7rem] border-b border-r border-gray-100 p-1.5"
                            :class="[
                                cell ? 'cursor-pointer hover:bg-gray-50' : 'bg-gray-50/50',
                                cell && cell.date === selectedDate ? 'ring-2 ring-inset ring-indigo-400' : '',
                            ]"
                            @click="cell && (selectedDate = cell.date)"
                        >
                            <template v-if="cell">
                                <div class="mb-1 text-xs font-medium text-gray-500">{{ cell.day }}</div>
                                <div class="space-y-1">
                                    <div
                                        v-for="item in cell.items.slice(0, 3)"
                                        :key="item.id"
                                        class="truncate rounded px-1 py-0.5 text-[11px] leading-tight"
                                        :class="item.status === 'odwolane' ? 'line-through opacity-50 ring-1 ring-inset ring-gray-400 grayscale' : ''"
                                        :style="{
                                            backgroundColor: item.type_color,
                                            color: readableTextColor(item.type_color),
                                        }"
                                    >
                                        {{ item.start_time }} {{ item.type_name }}
                                    </div>
                                    <div v-if="cell.items.length > 3" class="text-[11px] text-gray-400">
                                        +{{ cell.items.length - 3 }} więcej
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- Szczegóły dnia -->
                <div v-if="selectedDate" class="rounded-lg bg-white p-5 shadow-sm">
                    <h3 class="text-sm font-semibold text-gray-700">Zajęcia — {{ selectedDate }}</h3>
                    <table v-if="selectedItems.length" class="mt-3 min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="text-left text-xs font-medium uppercase tracking-wide text-gray-400">
                            <tr>
                                <th class="py-2 pr-4">Godzina</th>
                                <th class="py-2 pr-4">Zajęcia</th>
                                <th class="py-2 pr-4">Trener</th>
                                <th class="py-2 pr-4">Miejsca</th>
                                <th class="py-2 pr-4">Status</th>
                                <th class="py-2 text-right">Akcja</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <template v-for="item in selectedItems" :key="item.id">
                                <tr :class="item.status === 'odwolane' ? 'opacity-60' : ''">
                                    <td class="py-2 pr-4 text-gray-700" :class="item.status === 'odwolane' ? 'line-through' : ''">
                                        {{ item.start_time }}–{{ item.end_time }}
                                    </td>
                                    <td class="py-2 pr-4">
                                        <span class="inline-flex items-center gap-2">
                                            <span class="inline-block h-2.5 w-2.5 rounded-full" :style="{ backgroundColor: item.type_color }" />
                                            {{ item.type_name }}
                                        </span>
                                    </td>
                                    <td class="py-2 pr-4 text-gray-600">{{ item.trainer_name || '—' }}</td>
                                    <td class="py-2 pr-4 text-gray-600">
                                        <button
                                            type="button"
                                            class="font-medium text-indigo-600 hover:text-indigo-800"
                                            @click="toggleRoster(item.id)"
                                        >
                                            {{ item.confirmed_count }}/{{ item.capacity }}
                                        </button>
                                        <span v-if="item.waitlist_count" class="ml-1 text-xs text-blue-700">
                                            +{{ item.waitlist_count }} ocz.
                                        </span>
                                    </td>
                                    <td class="py-2 pr-4 text-gray-600">
                                        <template v-if="item.status === 'odwolane'">
                                            <span class="font-medium text-red-700">Odwołane</span>
                                            <span v-if="item.cancellation_reason"> — {{ item.cancellation_reason }}</span>
                                        </template>
                                        <span v-else>Planowane</span>
                                    </td>
                                    <td class="py-2 text-right">
                                        <button
                                            v-if="item.status !== 'odwolane'"
                                            type="button"
                                            class="text-xs font-medium text-red-600 hover:text-red-800"
                                            @click="cancelOccurrence(item)"
                                        >
                                            Odwołaj
                                        </button>
                                        <button
                                            v-else
                                            type="button"
                                            class="text-xs font-medium text-indigo-600 hover:text-indigo-800"
                                            @click="restoreOccurrence(item)"
                                        >
                                            Przywróć
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="expandedId === item.id" class="bg-gray-50/60">
                                    <td colspan="6" class="px-3 py-3">
                                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-400">
                                            Zapisani ({{ item.reservations.length }})
                                        </div>
                                        <table v-if="item.reservations.length" class="mt-2 min-w-full text-xs">
                                            <thead class="text-left text-gray-400">
                                                <tr>
                                                    <th class="py-1 pr-4">Klient</th>
                                                    <th class="py-1 pr-4">Status</th>
                                                    <th class="py-1 pr-4">Zgłoszenie</th>
                                                    <th class="py-1 pr-4">Potwierdzenie</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr v-for="(res, i) in item.reservations" :key="i" class="border-t border-gray-100">
                                                    <td class="py-1 pr-4 text-gray-700">{{ res.client_name }}</td>
                                                    <td class="py-1 pr-4">
                                                        <span
                                                            class="inline-flex rounded-full px-2 py-0.5 font-medium"
                                                            :class="reservationBadge[res.status] ?? 'bg-gray-100 text-gray-600'"
                                                        >
                                                            {{ res.status_label }}
                                                        </span>
                                                    </td>
                                                    <td class="py-1 pr-4 text-gray-500">{{ res.reported_at || '—' }}</td>
                                                    <td class="py-1 pr-4 text-gray-500">{{ res.confirmed_at || '—' }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                        <p v-else class="mt-1 text-xs text-gray-400">Nikt jeszcze się nie zapisał.</p>
                                        <p v-if="item.waitlist_count" class="mt-2 text-xs text-blue-700">
                                            Lista oczekujących ({{ item.waitlist_count }}) — kolejność wg daty potwierdzenia
                                            (kto pierwszy zapłacił).
                                        </p>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                    <p v-else class="mt-2 text-sm text-gray-400">Brak zajęć tego dnia.</p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
