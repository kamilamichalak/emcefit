<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { readableTextColor } from '@/Utils/color';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    month: { type: Object, required: true },
    weekdays: { type: Array, default: () => [] },
    groups: { type: Array, default: () => [] },
    // Wszystkie zajęcia widoku są dziedziczone z wcześniejszego miesiąca (read-only).
    patternInherited: { type: Boolean, default: false },
    inheritedFromLabel: { type: String, default: null },
    // Kolejny miesiąc ma już WŁASNY wzorzec (kopiowanie do niego = nadpisanie).
    nextMonthHasOwnPattern: { type: Boolean, default: false },
});

const editable = computed(() => !props.patternInherited);

const columns = computed(() =>
    props.weekdays.map((day) => ({
        ...day,
        items: props.groups
            .filter((group) => group.weekday === day.value)
            .sort((a, b) => a.start_time.localeCompare(b.start_time)),
    })),
);

const goToMonth = (value) => {
    router.get(route('admin.class-groups.index', { month: value }), {}, { preserveScroll: true });
};

const remove = (group) => {
    if (confirm(`Usunąć „${group.type_name}” (${group.start_time})?`)) {
        router.delete(route('admin.class-groups.destroy', group.id), { preserveScroll: true });
    }
};

const copyForm = useForm({ month: props.month.value, force: false });

// Skopiuj wzorzec DO wskazanego miesiąca (target), tak żeby stał się edytowalny.
const copyInto = (targetValue, targetLabel, targetHasOwnPattern) => {
    const question = targetHasOwnPattern
        ? `${targetLabel} ma już własny wzorzec i zostanie NADPISANY. Kontynuować?`
        : `Skopiować wzorzec na ${targetLabel}?\n\nWzorzec dziedziczony zostanie zamknięty, a na ${targetLabel} powstanie jego kopia do niezależnej edycji.`;

    if (!confirm(question)) return;

    copyForm.month = targetValue;
    copyForm.force = targetHasOwnPattern;
    copyForm.post(route('admin.class-groups.copy'));
};

const makeThisMonthEditable = () =>
    copyInto(props.month.value, props.month.label, false);

const copyToNextMonth = () =>
    copyInto(props.month.next, props.month.nextLabel, props.nextMonthHasOwnPattern);

const textOn = readableTextColor;
</script>

<template>
    <Head title="Wzorzec grafiku" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Wzorzec tygodniowy grafiku</h2>
                <div class="flex flex-wrap items-center gap-2">
                    <button
                        v-if="editable && groups.length"
                        type="button"
                        :disabled="copyForm.processing"
                        class="inline-flex items-center rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                        @click="copyToNextMonth"
                    >
                        Skopiuj wzorzec na <span class="ml-1 capitalize">{{ month.nextLabel }}</span>
                    </button>
                    <Link
                        v-if="editable"
                        :href="route('admin.class-groups.create', { month: month.value })"
                        class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500"
                    >
                        Dodaj zajęcia
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl space-y-4 sm:px-6 lg:px-8">
                <div
                    v-if="$page.props.flash?.success"
                    class="rounded-md bg-green-50 p-4 text-sm text-green-800"
                >
                    {{ $page.props.flash.success }}
                </div>
                <div
                    v-if="$page.props.flash?.warning"
                    class="flex flex-wrap items-center justify-between gap-3 rounded-md bg-amber-50 p-4 text-sm text-amber-800"
                >
                    <span>{{ $page.props.flash.warning }}</span>
                    <button
                        type="button"
                        class="rounded-md border border-amber-300 bg-white px-3 py-1 text-xs font-semibold text-amber-800 hover:bg-amber-100"
                        @click="copyToNextMonth"
                    >
                        Skopiuj z nadpisaniem
                    </button>
                </div>
                <div
                    v-if="$page.props.flash?.error"
                    class="rounded-md bg-red-50 p-4 text-sm text-red-800"
                >
                    {{ $page.props.flash.error }}
                </div>

                <!-- Wybór miesiąca -->
                <div class="flex items-center justify-center gap-4">
                    <button
                        type="button"
                        class="rounded-md border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50"
                        @click="goToMonth(month.prev)"
                    >
                        ‹ Poprzedni
                    </button>
                    <div class="w-44 text-center text-sm font-semibold capitalize text-gray-800">
                        {{ month.label }}
                    </div>
                    <button
                        type="button"
                        class="rounded-md border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50"
                        @click="goToMonth(month.next)"
                    >
                        Następny ›
                    </button>
                </div>

                <!-- Baner: wzorzec dziedziczony -->
                <div
                    v-if="patternInherited"
                    class="flex flex-wrap items-center justify-between gap-3 rounded-md border border-indigo-200 bg-indigo-50 p-4 text-sm text-indigo-900"
                >
                    <span>
                        Wzorzec dziedziczony z: <strong class="capitalize">{{ inheritedFromLabel }}</strong>.
                        Widok tylko do odczytu — aby edytować <span class="capitalize">{{ month.label }}</span> niezależnie, skopiuj wzorzec.
                    </span>
                    <button
                        type="button"
                        :disabled="copyForm.processing"
                        class="shrink-0 rounded-md bg-indigo-600 px-4 py-2 text-xs font-semibold text-white hover:bg-indigo-500 disabled:opacity-50"
                        @click="makeThisMonthEditable"
                    >
                        Skopiuj wzorzec na <span class="capitalize">{{ month.label }}</span>
                    </button>
                </div>
                <p v-else class="text-center text-xs text-gray-500">
                    Wzorzec obowiązuje dla wskazanego miesiąca. Generowanie konkretnych terminów to osobny krok.
                </p>

                <!-- Siatka pon–pt -->
                <div class="grid gap-3 md:grid-cols-5" :class="patternInherited ? 'opacity-60' : ''">
                    <div
                        v-for="col in columns"
                        :key="col.value"
                        class="rounded-lg bg-white shadow-sm"
                    >
                        <div class="flex items-center justify-between border-b border-gray-100 px-3 py-2">
                            <span class="text-sm font-semibold text-gray-700">{{ col.label }}</span>
                            <Link
                                v-if="editable"
                                :href="route('admin.class-groups.create', { month: month.value, weekday: col.value })"
                                class="text-xs text-indigo-600 hover:text-indigo-900"
                            >
                                + dodaj
                            </Link>
                        </div>

                        <div class="space-y-2 p-3">
                            <div
                                v-for="item in col.items"
                                :key="item.id"
                                class="rounded-md p-2 text-xs"
                                :style="{ backgroundColor: item.type_color, color: textOn(item.type_color) }"
                            >
                                <div class="font-semibold">
                                    {{ item.start_time }}–{{ item.end_time }}
                                </div>
                                <div>{{ item.type_name }}</div>
                                <div class="opacity-90">
                                    {{ item.capacity }} miejsc<template v-if="item.trainer_name"> · {{ item.trainer_name }}</template>
                                </div>
                                <div v-if="editable" class="mt-1 flex gap-2">
                                    <Link
                                        :href="route('admin.class-groups.edit', item.id)"
                                        class="underline decoration-white/50 hover:decoration-current"
                                    >
                                        Edytuj
                                    </Link>
                                    <button type="button" class="underline decoration-white/50 hover:decoration-current" @click="remove(item)">
                                        Usuń
                                    </button>
                                </div>
                            </div>

                            <p v-if="col.items.length === 0" class="py-4 text-center text-xs text-gray-400">
                                brak zajęć
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
