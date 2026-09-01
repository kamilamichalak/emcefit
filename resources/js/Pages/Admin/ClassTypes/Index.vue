<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

defineProps({
    classTypes: { type: Array, default: () => [] },
});

const remove = (classType) => {
    if (confirm(`Usunąć typ zajęć „${classType.name}”?`)) {
        router.delete(route('admin.class-types.destroy', classType.id), { preserveScroll: true });
    }
};
</script>

<template>
    <Head title="Typy zajęć" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Typy zajęć</h2>
                <Link
                    :href="route('admin.class-types.create')"
                    class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-500"
                >
                    Dodaj typ zajęć
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-5xl space-y-4 sm:px-6 lg:px-8">
                <div
                    v-if="$page.props.flash?.success"
                    class="rounded-md bg-green-50 p-4 text-sm text-green-800"
                >
                    {{ $page.props.flash.success }}
                </div>

                <p class="text-sm text-gray-500">
                    Słownik typów zajęć. Buduje się go niezależnie od grafiku — z tej listy
                    admin wybiera przy układaniu wzorca tygodniowego.
                </p>

                <div class="overflow-x-auto bg-white shadow-sm sm:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-3">Nazwa</th>
                                <th class="px-4 py-3">Domyślny limit</th>
                                <th class="px-4 py-3">Wymagany sprzęt</th>
                                <th class="px-4 py-3">Opis</th>
                                <th class="px-4 py-3 text-right">Akcje</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="classType in classTypes" :key="classType.id">
                                <td class="px-4 py-3 font-medium text-gray-900">
                                    <span class="flex items-center gap-2">
                                        <span
                                            class="inline-block h-3 w-3 shrink-0 rounded-full ring-1 ring-black/10"
                                            :style="{ backgroundColor: classType.color }"
                                        />
                                        {{ classType.name }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ classType.default_capacity }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ classType.required_equipment || '—' }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ classType.description || '—' }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-3">
                                        <Link
                                            :href="route('admin.class-types.edit', classType.id)"
                                            class="text-indigo-600 hover:text-indigo-900"
                                        >
                                            Edytuj
                                        </Link>
                                        <button
                                            type="button"
                                            class="text-red-600 hover:text-red-900"
                                            @click="remove(classType)"
                                        >
                                            Usuń
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="classTypes.length === 0">
                                <td colspan="5" class="px-4 py-8 text-center text-gray-500">
                                    Brak typów zajęć. Dodaj pierwszy.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
