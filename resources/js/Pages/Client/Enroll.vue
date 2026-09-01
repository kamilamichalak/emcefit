<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { readableTextColor } from '@/Utils/color';
import { Head, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

const props = defineProps({
    month: { type: Object, required: true },
    monthOptions: { type: Array, default: () => [] },
    weekdays: { type: Array, default: () => [] },
    classGroups: { type: Array, default: () => [] },
    pricing: { type: Array, default: () => [] },
});

const money = (value) =>
    new Intl.NumberFormat('pl-PL', { style: 'currency', currency: 'PLN' }).format(Number(value));

const selected = reactive(new Set());

const toggle = (id) => {
    selected.has(id) ? selected.delete(id) : selected.add(id);
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

const changeMonth = (value) => {
    if (value === props.month.value) return;
    selected.clear();
    router.get(route('client.enrollment.create', { month: value }), {}, { preserveScroll: true });
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
                <!-- Wybór miesiąca -->
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

                <div class="grid gap-4 lg:grid-cols-[1fr_18rem]">
                    <!-- Siatka zajęć pon–pt -->
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                        <div
                            v-for="col in columns"
                            :key="col.value"
                            class="rounded-lg bg-white shadow-sm"
                        >
                            <div class="border-b border-gray-100 px-3 py-2 text-sm font-semibold text-gray-700">
                                {{ col.label }}
                            </div>
                            <div class="space-y-2 p-3">
                                <label
                                    v-for="item in col.items"
                                    :key="item.id"
                                    class="flex cursor-pointer gap-2 rounded-md border p-2 text-xs"
                                    :class="selected.has(item.id) ? 'border-indigo-400 bg-indigo-50' : 'border-gray-200'"
                                >
                                    <input
                                        type="checkbox"
                                        class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                        :checked="selected.has(item.id)"
                                        @change="toggle(item.id)"
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

                                <p v-if="col.items.length === 0" class="py-4 text-center text-xs text-gray-400">
                                    brak zajęć
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Podsumowanie / cena -->
                    <div class="h-fit rounded-lg bg-white p-5 shadow-sm lg:sticky lg:top-6">
                        <div class="text-sm text-gray-500">Wybrane zajęcia / tydzień</div>
                        <div class="mt-1 text-3xl font-semibold text-gray-900">{{ count }}</div>

                        <div class="mt-4 border-t border-gray-100 pt-4">
                            <template v-if="priceInfo.state === 'empty'">
                                <p class="text-sm text-gray-500">
                                    Zaznacz zajęcia, aby zobaczyć cenę karnetu.
                                </p>
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
                                        Maksymalnie {{ maxVariant.sessions_per_week }} zajęć
                                        ({{ money(maxVariant.price) }}). Odznacz część zajęć.
                                    </template>
                                </p>
                            </template>
                        </div>

                        <p class="mt-4 text-xs text-gray-400">
                            Wybór konkretnych dat, planowane nieobecności i zgłoszenie chęci
                            udziału — w kolejnym kroku.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
