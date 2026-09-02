<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ClassTypeBadge from '@/Components/ClassTypeBadge.vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    pendingPayments: { type: Array, default: () => [] },
    pendingPaymentsTotal: { type: [Number, String], default: 0 },
    unpaidSoon: { type: Array, default: () => [] },
    enrollmentUpcoming: { type: Object, default: () => ({ value: '', label: '', open: false }) },
    clientsNotEnrolled: { type: Array, default: () => [] },
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

const openEnrollment = () => {
    router.patch(
        route('admin.schedule.enrollment'),
        { month: props.enrollmentUpcoming.value, open: true },
        { preserveScroll: true },
    );
};

const daysWaitingClass = (days) =>
    days >= 7 ? 'text-red-700 font-semibold' : days >= 3 ? 'text-amber-700' : 'text-gray-600';
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
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div class="rounded-lg bg-white p-5 shadow-sm">
                        <div class="text-sm text-gray-500">Niezaksięgowane płatności</div>
                        <div class="mt-1 text-3xl font-semibold text-gray-900">
                            {{ pendingPayments.length }}
                        </div>
                        <div class="text-xs text-gray-500">na kwotę {{ money(pendingPaymentsTotal) }}</div>
                    </div>
                    <div class="rounded-lg bg-white p-5 shadow-sm">
                        <div class="text-sm text-gray-500">Zapisy na <span class="capitalize">{{ enrollmentUpcoming.label }}</span></div>
                        <div
                            class="mt-1 text-2xl font-semibold"
                            :class="enrollmentUpcoming.open ? 'text-green-700' : 'text-gray-500'"
                        >
                            {{ enrollmentUpcoming.open ? 'otwarte' : 'zamknięte' }}
                        </div>
                        <button
                            v-if="!enrollmentUpcoming.open"
                            type="button"
                            class="mt-2 rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-500"
                            @click="openEnrollment"
                        >
                            Otwórz zapisy
                        </button>
                    </div>
                    <div class="rounded-lg bg-white p-5 shadow-sm">
                        <div class="text-sm text-gray-500">Klienci aktywni</div>
                        <div class="mt-1 text-3xl font-semibold text-gray-900">
                            {{ clientsActive }}
                            <span class="text-base font-normal text-gray-400">/ {{ clientsTotal }}</span>
                        </div>
                    </div>
                </div>

                <!-- Rezerwacje bez opłaty, a zajęcia < 24h -->
                <div v-if="unpaidSoon.length" class="rounded-lg border border-red-200 bg-white shadow-sm">
                    <div class="border-b border-red-100 bg-red-50 px-5 py-3 text-sm font-semibold text-red-800">
                        ⚠ Rezerwacje bez opłaty — zajęcia w ciągu 24 h
                    </div>
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                            <tr>
                                <th class="px-5 py-2">Klient</th>
                                <th class="px-5 py-2">Zajęcia</th>
                                <th class="px-5 py-2">Start</th>
                                <th class="px-5 py-2">Za ile</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="row in unpaidSoon" :key="row.id">
                                <td class="px-5 py-2">
                                    <Link :href="route('admin.clients.show', row.client_id)" class="text-indigo-600 hover:text-indigo-900">
                                        {{ row.client_name }}
                                    </Link>
                                </td>
                                <td class="px-5 py-2">
                                    <span class="inline-flex items-center gap-2 text-gray-700">
                                        <ClassTypeBadge :color="row.type_color" :icon="row.type_icon" size="sm" />
                                        {{ row.type_name }}
                                    </span>
                                </td>
                                <td class="px-5 py-2 capitalize text-gray-600">{{ row.starts_at_label }}</td>
                                <td class="px-5 py-2 font-semibold text-red-700">{{ row.hours_left }} h</td>
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
                                <th class="px-5 py-2">Czeka</th>
                                <th class="px-5 py-2">Tytuł przelewu</th>
                                <th class="px-5 py-2 text-right">Akcja</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="row in pendingPayments" :key="row.id">
                                <td class="px-5 py-2">
                                    <Link :href="route('admin.clients.show', row.client_id)" class="text-indigo-600 hover:text-indigo-900">
                                        {{ row.client_name }}
                                    </Link>
                                </td>
                                <td class="px-5 py-2 text-gray-600">{{ money(row.amount) }}</td>
                                <td class="px-5 py-2 text-gray-600">{{ row.reported_date || '—' }}</td>
                                <td class="px-5 py-2" :class="daysWaitingClass(row.days_waiting)">{{ row.days_waiting }} dni</td>
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
                                <td colspan="6" class="px-5 py-6 text-center text-gray-500">
                                    Wszystkie płatności zaksięgowane.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Kto jeszcze się nie zapisał na nadchodzący miesiąc -->
                <div class="rounded-lg bg-white shadow-sm">
                    <div class="border-b border-gray-100 px-5 py-3 text-sm font-semibold text-gray-700">
                        Aktywni klienci bez zgłoszenia na <span class="capitalize">{{ enrollmentUpcoming.label }}</span>
                    </div>
                    <div class="px-5 py-4">
                        <p v-if="!enrollmentUpcoming.open" class="text-sm text-gray-500">
                            Zapisy na ten miesiąc nie są jeszcze otwarte — lista pojawi się po ich otwarciu.
                        </p>
                        <p v-else-if="clientsNotEnrolled.length === 0" class="text-sm text-gray-500">
                            Wszyscy aktywni klienci mają już zgłoszenie na ten miesiąc.
                        </p>
                        <div v-else class="flex flex-wrap gap-2">
                            <Link
                                v-for="c in clientsNotEnrolled"
                                :key="c.id"
                                :href="route('admin.clients.show', c.id)"
                                class="rounded-full border border-gray-300 px-3 py-1 text-sm text-gray-700 hover:bg-gray-50"
                            >
                                {{ c.name }}
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
