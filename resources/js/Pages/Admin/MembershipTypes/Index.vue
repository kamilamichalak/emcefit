<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';

const props = defineProps({
    membershipTypes: { type: Array, default: () => [] },
});

const money = (value) =>
    new Intl.NumberFormat('pl-PL', { style: 'currency', currency: 'PLN' }).format(Number(value));

const editingId = ref(null);
const draft = reactive({ price: '', processing: false, error: '' });

const startEdit = (type) => {
    editingId.value = type.id;
    draft.price = String(type.price);
    draft.error = '';
};

const cancelEdit = () => {
    editingId.value = null;
    draft.error = '';
};

const save = (type) => {
    draft.processing = true;
    draft.error = '';
    router.patch(
        route('admin.membership-types.price', type.id),
        { price: draft.price },
        {
            preserveScroll: true,
            onSuccess: () => {
                editingId.value = null;
            },
            onError: (errors) => {
                draft.error = errors.price ?? 'Nie udało się zapisać ceny.';
            },
            onFinish: () => {
                draft.processing = false;
            },
        },
    );
};
</script>

<template>
    <Head title="Cennik karnetów" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Cennik karnetów</h2>
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
                    Na tym etapie edytowalna jest wyłącznie <strong>cena</strong>. Pozostałe
                    atrybuty karnetu pochodzą z danych startowych. Zmiana ceny dotyczy tylko
                    <strong>nowych</strong> karnetów — karnety już wystawione i płatności w toku
                    zostają bez zmian.
                </p>

                <div class="overflow-x-auto bg-white shadow-sm sm:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-4 py-3">Nazwa</th>
                                <th class="px-4 py-3">Tryb</th>
                                <th class="px-4 py-3">Zajęcia/tydz.</th>
                                <th class="px-4 py-3">Wejścia</th>
                                <th class="px-4 py-3">Ważność</th>
                                <th class="px-4 py-3 text-right">Cena</th>
                                <th class="px-4 py-3 text-right">Akcja</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="type in membershipTypes" :key="type.id">
                                <td class="px-4 py-3 font-medium text-gray-900">{{ type.name }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ type.mode }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ type.sessions_per_week ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ type.entry_count ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ type.validity }}</td>
                                <td class="px-4 py-3 text-right">
                                    <template v-if="editingId === type.id">
                                        <div class="flex flex-col items-end gap-1">
                                            <div class="flex items-center justify-end gap-1">
                                                <input
                                                    v-model="draft.price"
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    class="w-28 rounded-md border-gray-300 text-right text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                    @keyup.enter="save(type)"
                                                    @keyup.esc="cancelEdit"
                                                />
                                                <span class="text-gray-500">zł</span>
                                            </div>
                                            <p v-if="draft.error" class="text-xs text-red-600">{{ draft.error }}</p>
                                        </div>
                                    </template>
                                    <span v-else class="font-semibold text-gray-900">{{ money(type.price) }}</span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <template v-if="editingId === type.id">
                                        <div class="flex justify-end gap-3">
                                            <button
                                                type="button"
                                                class="text-indigo-600 hover:text-indigo-900 disabled:opacity-50"
                                                :disabled="draft.processing"
                                                @click="save(type)"
                                            >
                                                Zapisz
                                            </button>
                                            <button
                                                type="button"
                                                class="text-gray-500 hover:text-gray-700"
                                                @click="cancelEdit"
                                            >
                                                Anuluj
                                            </button>
                                        </div>
                                    </template>
                                    <button
                                        v-else
                                        type="button"
                                        class="text-indigo-600 hover:text-indigo-900"
                                        @click="startEdit(type)"
                                    >
                                        Zmień cenę
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="membershipTypes.length === 0">
                                <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                                    Brak typów karnetów.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
