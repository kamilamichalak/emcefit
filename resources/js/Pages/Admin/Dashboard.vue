<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

defineProps({
    endingMemberships: { type: Array, default: () => [] },
    pendingPayments: { type: Array, default: () => [] },
    pendingPaymentsTotal: { type: [Number, String], default: 0 },
    openMembershipsThisMonth: { type: Number, default: 0 },
    openMembershipsLimit: { type: Number, default: 20 },
    clientsActive: { type: Number, default: 0 },
    clientsTotal: { type: Number, default: 0 },
});

const money = (value) =>
    new Intl.NumberFormat('pl-PL', { style: 'currency', currency: 'PLN' }).format(Number(value));

const settlePayment = (payment) => {
    router.patch(
        route('admin.payments.status', payment.id),
        { status: 'zaksiegowana' },
        { preserveScroll: true },
    );
};
</script>

<template>
    <Head title="Pulpit" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Pulpit admina</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <div
                    v-if="$page.props.flash?.success"
                    class="rounded-md bg-green-50 p-4 text-sm text-green-800"
                >
                    {{ $page.props.flash.success }}
                </div>

                <!-- Kafelki -->
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="rounded-lg bg-white p-5 shadow-sm">
                        <div class="text-sm text-gray-500">Karnety kończące się (7 dni)</div>
                        <div class="mt-1 text-3xl font-semibold text-gray-900">
                            {{ endingMemberships.length }}
                        </div>
                    </div>
                    <div class="rounded-lg bg-white p-5 shadow-sm">
                        <div class="text-sm text-gray-500">Niezaksięgowane płatności</div>
                        <div class="mt-1 text-3xl font-semibold text-gray-900">
                            {{ pendingPayments.length }}
                        </div>
                        <div class="text-xs text-gray-500">na kwotę {{ money(pendingPaymentsTotal) }}</div>
                    </div>
                    <div class="rounded-lg bg-white p-5 shadow-sm">
                        <div class="text-sm text-gray-500">Abonamenty otwarte (ten miesiąc)</div>
                        <div class="mt-1 text-3xl font-semibold text-gray-900">
                            {{ openMembershipsThisMonth }}
                            <span class="text-base font-normal text-gray-400">/ {{ openMembershipsLimit }}</span>
                        </div>
                        <div class="text-xs text-gray-500">
                            licznik informacyjny — limit nie jest egzekwowany (sekcja 8a)
                        </div>
                    </div>
                    <div class="rounded-lg bg-white p-5 shadow-sm">
                        <div class="text-sm text-gray-500">Klienci aktywni</div>
                        <div class="mt-1 text-3xl font-semibold text-gray-900">
                            {{ clientsActive }}
                            <span class="text-base font-normal text-gray-400">/ {{ clientsTotal }}</span>
                        </div>
                    </div>
                </div>

                <!-- Karnety kończące się -->
                <div class="rounded-lg bg-white shadow-sm">
                    <div class="border-b border-gray-100 px-5 py-3 text-sm font-semibold text-gray-700">
                        Karnety kończące się w ciągu 7 dni
                    </div>
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-5 py-2">Klient</th>
                                <th class="px-5 py-2">Karnet</th>
                                <th class="px-5 py-2">Koniec</th>
                                <th class="px-5 py-2">Pozostało dni</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="row in endingMemberships" :key="row.id">
                                <td class="px-5 py-2">
                                    <Link
                                        :href="route('admin.clients.show', row.client_id)"
                                        class="text-indigo-600 hover:text-indigo-900"
                                    >
                                        {{ row.client_name }}
                                    </Link>
                                </td>
                                <td class="px-5 py-2 text-gray-600">{{ row.type_name }}</td>
                                <td class="px-5 py-2 text-gray-600">{{ row.end_date }}</td>
                                <td class="px-5 py-2 text-gray-600">{{ row.days_left }}</td>
                            </tr>
                            <tr v-if="endingMemberships.length === 0">
                                <td colspan="4" class="px-5 py-6 text-center text-gray-500">
                                    Brak karnetów kończących się w tym oknie.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Niezaksięgowane płatności -->
                <div class="rounded-lg bg-white shadow-sm">
                    <div class="border-b border-gray-100 px-5 py-3 text-sm font-semibold text-gray-700">
                        Niezaksięgowane płatności (przelewy do sprawdzenia na wyciągu)
                    </div>
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-5 py-2">Klient</th>
                                <th class="px-5 py-2">Kwota</th>
                                <th class="px-5 py-2">Zgłoszono</th>
                                <th class="px-5 py-2">Tytuł przelewu</th>
                                <th class="px-5 py-2 text-right">Akcja</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="row in pendingPayments" :key="row.id">
                                <td class="px-5 py-2">
                                    <Link
                                        :href="route('admin.clients.show', row.client_id)"
                                        class="text-indigo-600 hover:text-indigo-900"
                                    >
                                        {{ row.client_name }}
                                    </Link>
                                </td>
                                <td class="px-5 py-2 text-gray-600">{{ money(row.amount) }}</td>
                                <td class="px-5 py-2 text-gray-600">{{ row.reported_date || '—' }}</td>
                                <td class="px-5 py-2 text-gray-600">{{ row.transfer_title || '—' }}</td>
                                <td class="px-5 py-2 text-right">
                                    <button
                                        type="button"
                                        class="rounded-md bg-green-600 px-3 py-1 text-xs font-semibold text-white hover:bg-green-500"
                                        @click="settlePayment(row)"
                                    >
                                        Zaksięguj
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="pendingPayments.length === 0">
                                <td colspan="5" class="px-5 py-6 text-center text-gray-500">
                                    Wszystkie płatności zaksięgowane.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
