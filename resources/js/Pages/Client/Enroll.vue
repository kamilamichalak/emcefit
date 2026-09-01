<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import InputError from '@/Components/InputError.vue';
import { readableTextColor } from '@/Utils/color';
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
    pricing: { type: Array, default: () => [] },
});

const money = (value) =>
    new Intl.NumberFormat('pl-PL', { style: 'currency', currency: 'PLN' }).format(Number(value));

const selected = reactive(new Set());
// { [class_schedule_id]: true }  — domyślnie "będę"
const attendance = reactive({});

const occurrencesFor = (groupId) => props.occurrencesByGroup[groupId] ?? [];

const toggleGroup = (groupId) => {
    if (selected.has(groupId)) {
        selected.delete(groupId);
    } else {
        selected.add(groupId);
        for (const occ of occurrencesFor(groupId)) {
            if (!occ.cancelled && attendance[occ.id] === undefined) attendance[occ.id] = true;
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
    props.pricing.reduce((max, p) => (p.sessions_per_week > (max?.sessions_per_week ?? 0) ? p : max), null),
);

const priceInfo = computed(() => {
    if (count.value === 0) return { state: 'empty' };
    const variant = props.pricing.find((p) => p.sessions_per_week === count.value);
    if (variant) return { state: 'ok', name: variant.name, price: variant.price };
    return { state: 'no_variant' };
});

// class_schedule_id, na które klient zadeklarował nieobecność
const absences = computed(() => {
    const ids = [];
    for (const groupId of selected) {
        for (const occ of occurrencesFor(groupId)) {
            if (!occ.cancelled && attendance[occ.id] === false) ids.push(occ.id);
        }
    }
    return ids;
});

const attendingCount = computed(() => {
    let n = 0;
    for (const groupId of selected) {
        for (const occ of occurrencesFor(groupId)) {
            if (!occ.cancelled && attendance[occ.id] !== false) n++;
        }
    }
    return n;
});

const changeMonth = (value) => {
    if (value === props.month.value) return;
    selected.clear();
    router.get(route('client.enrollment.create', { month: value }), {}, { preserveScroll: true });
};

const form = useForm({ month: props.month.value, class_group_ids: [], absences: [] });

const canSubmit = computed(
    () =>
        props.enrollmentOpen &&
        props.scheduleGenerated &&
        priceInfo.value.state === 'ok' &&
        !form.processing,
);

const alreadyEnrolled = computed(() =>
    (form.errors.class_group_ids ?? '').includes('Masz już zgłoszenie'),
);

const submit = () => {
    form.month = props.month.value;
    form.class_group_ids = [...selected];
    form.absences = absences.value;
    form.post(route('client.enrollment.store'));
};
</script>

<template>
    <Head title="Zapisy na zajęcia" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Zapisz się na zajęcia</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-6xl space-y-4 sm:px-6 lg:px-8">
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
                <p v-if="alreadyEnrolled" class="text-sm">
                    <Link
                        :href="route('client.classes.index', { month: month.value })"
                        class="font-medium text-indigo-600 hover:text-indigo-800"
                    >
                        Zobacz szczegóły swojego zgłoszenia →
                    </Link>
                </p>

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
                                                class="ml-1 inline-flex rounded px-1 py-0.5"
                                                :style="{
                                                    backgroundColor: item.type_color,
                                                    color: readableTextColor(item.type_color),
                                                }"
                                            >
                                                {{ item.type_name }}
                                            </span>
                                            <span class="mt-0.5 block text-gray-500">
                                                {{ item.free_spots }} wolnych miejsc
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
                            <template v-else-if="priceInfo.state === 'ok'">
                                <div class="text-sm text-gray-500">{{ priceInfo.name }}</div>
                                <div class="mt-1 text-2xl font-semibold text-gray-900">
                                    {{ money(priceInfo.price) }}
                                    <span class="text-sm font-normal text-gray-400">/ miesiąc</span>
                                </div>
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

                        <div v-if="count > 0 && scheduleGenerated" class="mt-3 text-xs text-gray-500">
                            Terminy: {{ attendingCount }} „będę", {{ absences.length }} „nie będę"
                            (za nieobecności dostaniesz zajęcia do odrobienia).
                        </div>

                        <PrimaryButton class="mt-4 w-full justify-center" :disabled="!canSubmit" @click="submit">
                            Zgłoś chęć udziału
                        </PrimaryButton>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
