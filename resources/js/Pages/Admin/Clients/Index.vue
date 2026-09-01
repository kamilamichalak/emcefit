<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive, watch } from 'vue';

const props = defineProps({
    clients: { type: Object, required: true },
    filters: { type: Object, default: () => ({ search: '', status: '' }) },
    statuses: { type: Array, default: () => [] },
});

const state = reactive({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
});

function debounce(fn, delay) {
    let timer;
    return (...args) => {
        clearTimeout(timer);
        timer = setTimeout(() => fn(...args), delay);
    };
}

const applyFilters = debounce(() => {
    router.get(
        route('admin.clients.index'),
        {
            search: state.search || undefined,
            status: state.status || undefined,
        },
        { preserveState: true, replace: true, preserveScroll: true },
    );
}, 300);

watch(state, applyFilters);

const toggleStatus = (client) => {
    router.patch(route('admin.clients.status', client.id), {}, { preserveScroll: true });
};
</script>

<template>
    <Head title="Klienci" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Klienci</h2>
                <Link
                    :href="route('admin.clients.create')"
                    class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-indigo-500"
                >
                    Dodaj klienta
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

                <div class="flex flex-wrap gap-3">
                    <input
                        v-model="state.search"
                        type="search"
                        placeholder="Szukaj: imię, e-mail, telefon"
                        class="w-72 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                    <select
                        v-model="state.status"
                        class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        <option value="">Wszystkie statusy</option>
                        <option
                            v-for="option in statuses"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                </div>

                <div class="overflow-x-auto bg-white shadow-sm sm:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead
                            class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500"
                        >
                            <tr>
                                <th class="px-4 py-3">Imię i nazwisko</th>
                                <th class="px-4 py-3">E-mail</th>
                                <th class="px-4 py-3">Telefon</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Dołączył</th>
                                <th class="px-4 py-3 text-right">Akcje</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="client in clients.data" :key="client.id">
                                <td class="px-4 py-3 font-medium">
                                    <Link
                                        :href="route('admin.clients.show', client.id)"
                                        class="text-indigo-700 hover:text-indigo-900"
                                    >
                                        {{ client.name }}
                                    </Link>
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ client.email }}</td>
                                <td class="px-4 py-3 text-gray-600">
                                    {{ client.phone || '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="
                                            client.status === 'aktywny'
                                                ? 'bg-green-100 text-green-800'
                                                : 'bg-gray-100 text-gray-600'
                                        "
                                    >
                                        {{ client.status_label }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-gray-600">
                                    {{ client.join_date || '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-3">
                                        <Link
                                            :href="route('admin.clients.edit', client.id)"
                                            class="text-indigo-600 hover:text-indigo-900"
                                        >
                                            Edytuj
                                        </Link>
                                        <button
                                            type="button"
                                            class="text-gray-600 hover:text-gray-900"
                                            @click="toggleStatus(client)"
                                        >
                                            {{
                                                client.status === 'aktywny'
                                                    ? 'Dezaktywuj'
                                                    : 'Aktywuj'
                                            }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="clients.data.length === 0">
                                <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                                    Brak klientów.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Pagination :links="clients.links" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
