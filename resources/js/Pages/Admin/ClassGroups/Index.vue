<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { readableTextColor } from '@/Utils/color';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    month: { type: Object, required: true },
    weekdays: { type: Array, default: () => [] },
    groups: { type: Array, default: () => [] },
});

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

const textOn = readableTextColor;
</script>

<template>
    <Head title="Wzorzec grafiku" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Wzorzec tygodniowy grafiku</h2>
                <Link
                    :href="route('admin.class-groups.create', { month: month.value })"
                    class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500"
                >
                    Dodaj zajęcia
                </Link>
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
                <p class="text-center text-xs text-gray-500">
                    Wzorzec obowiązuje dla wskazanego miesiąca. Generowanie konkretnych terminów
                    i kopiowanie wzorca na nowy miesiąc to osobny krok.
                </p>

                <!-- Siatka pon–pt -->
                <div class="grid gap-3 md:grid-cols-5">
                    <div
                        v-for="col in columns"
                        :key="col.value"
                        class="rounded-lg bg-white shadow-sm"
                    >
                        <div class="flex items-center justify-between border-b border-gray-100 px-3 py-2">
                            <span class="text-sm font-semibold text-gray-700">{{ col.label }}</span>
                            <Link
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
                                <div class="mt-1 flex gap-2">
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
