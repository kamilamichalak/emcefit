<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    client: { type: Object, required: true },
    memberships: { type: Array, default: () => [] },
});

const money = (value) =>
    new Intl.NumberFormat('pl-PL', { style: 'currency', currency: 'PLN' }).format(Number(value));

const setPaymentStatus = (payment, status) => {
    router.patch(
        route('admin.payments.status', payment.id),
        { status },
        { preserveScroll: true },
    );
};

const deleteMembership = (membership) => {
    if (confirm('Usunąć ten karnet? Operacja jest możliwa tylko bez zaksięgowanej płatności.')) {
        router.delete(route('admin.memberships.destroy', membership.id), { preserveScroll: true });
    }
};

const badgeClass = (status) =>
    ({
        zaksiegowana: 'bg-green-100 text-green-800',
        oczekuje: 'bg-amber-100 text-amber-800',
        anulowana: 'bg-gray-100 text-gray-500',
    })[status] ?? 'bg-gray-100 text-gray-600';
</script>

<template>
    <Head :title="`Klient: ${client.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    {{ client.name }}
                    <span
                        class="ml-2 inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                        :class="client.status === 'aktywny' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'"
                    >
                        {{ client.status_label }}
                    </span>
                </h2>
                <div class="flex gap-3 text-sm">
                    <Link :href="route('admin.clients.edit', client.id)" class="text-indigo-600 hover:text-indigo-900">
                        Edytuj dane
                    </Link>
                    <Link :href="route('admin.clients.index')" class="text-gray-600 hover:text-gray-900">
                        Wróć do listy
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
                <div
                    v-if="$page.props.flash?.success"
                    class="rounded-md bg-green-50 p-4 text-sm text-green-800"
                >
                    {{ $page.props.flash.success }}
                </div>
                <div
                    v-if="$page.props.flash?.error"
                    class="rounded-md bg-red-50 p-4 text-sm text-red-800"
                >
                    {{ $page.props.flash.error }}
                </div>

                <!-- Dane klienta -->
                <div class="grid gap-x-8 gap-y-2 rounded-lg bg-white p-6 text-sm shadow-sm sm:grid-cols-2">
                    <div><span class="text-gray-500">E-mail:</span> {{ client.email }}</div>
                    <div><span class="text-gray-500">Telefon:</span> {{ client.phone || '—' }}</div>
                    <div><span class="text-gray-500">Data urodzenia:</span> {{ client.birth_date || '—' }}</div>
                    <div><span class="text-gray-500">Dołączył:</span> {{ client.join_date || '—' }}</div>
                    <div>
                        <span class="text-gray-500">Regulamin:</span>
                        {{ client.terms_accepted_at || 'nie zaakceptowano' }}
                    </div>
                    <div>
                        <span class="text-gray-500">Oświadczenie zdrowotne:</span>
                        {{ client.health_declaration_at || 'brak' }}
                    </div>
                </div>

                <!-- Karnety -->
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-800">Karnety</h3>
                        <Link
                            :href="route('admin.clients.memberships.create', client.id)"
                            class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500"
                        >
                            Przypisz karnet
                        </Link>
                    </div>

                    <p v-if="memberships.length === 0" class="rounded-lg bg-white p-6 text-sm text-gray-500 shadow-sm">
                        Klient nie ma jeszcze żadnego karnetu.
                    </p>

                    <div
                        v-for="membership in memberships"
                        :key="membership.id"
                        class="rounded-lg bg-white p-5 shadow-sm"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <div class="font-medium text-gray-900">
                                    {{ membership.type_name }}
                                    <span class="ml-1 text-xs font-normal text-gray-400">
                                        ({{ membership.mode_label }})
                                    </span>
                                </div>
                                <div class="mt-1 text-sm text-gray-600">
                                    Okres:
                                    {{ membership.start_date || '—' }} → {{ membership.end_date || '—' }}
                                    <template v-if="membership.first_entry_date">
                                        · 1. wejście: {{ membership.first_entry_date }}
                                    </template>
                                    <template v-if="membership.entries_remaining !== null">
                                        · wejścia pozostałe: {{ membership.entries_remaining }}
                                    </template>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <span
                                    class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="
                                        membership.is_paid
                                            ? 'bg-green-100 text-green-800'
                                            : membership.awaiting_payment
                                              ? 'bg-amber-100 text-amber-800'
                                              : 'bg-gray-100 text-gray-600'
                                    "
                                >
                                    {{
                                        membership.is_paid
                                            ? 'Opłacony'
                                            : membership.awaiting_payment
                                              ? 'Oczekuje na wpłatę'
                                              : 'Brak płatności'
                                    }}
                                </span>
                                <Link
                                    :href="route('admin.memberships.payments.create', membership.id)"
                                    class="rounded-md border border-gray-300 px-3 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50"
                                >
                                    Dodaj płatność
                                </Link>
                                <button
                                    v-if="!membership.is_paid"
                                    type="button"
                                    class="rounded-md px-2 py-1 text-xs text-red-600 hover:bg-red-50"
                                    @click="deleteMembership(membership)"
                                >
                                    Usuń
                                </button>
                            </div>
                        </div>

                        <!-- Płatności karnetu -->
                        <table
                            v-if="membership.payments.length"
                            class="mt-4 min-w-full divide-y divide-gray-200 text-sm"
                        >
                            <thead class="text-left text-xs font-medium uppercase tracking-wide text-gray-400">
                                <tr>
                                    <th class="py-2 pr-4">Kwota</th>
                                    <th class="py-2 pr-4">Zgłoszono</th>
                                    <th class="py-2 pr-4">Zaksięgowano</th>
                                    <th class="py-2 pr-4">Tytuł przelewu</th>
                                    <th class="py-2 pr-4">Status</th>
                                    <th class="py-2 text-right">Akcje</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="payment in membership.payments" :key="payment.id">
                                    <td class="py-2 pr-4 text-gray-700">{{ money(payment.amount) }}</td>
                                    <td class="py-2 pr-4 text-gray-600">{{ payment.reported_date || '—' }}</td>
                                    <td class="py-2 pr-4 text-gray-600">{{ payment.settled_date || '—' }}</td>
                                    <td class="py-2 pr-4 text-gray-600">{{ payment.transfer_title || '—' }}</td>
                                    <td class="py-2 pr-4">
                                        <span
                                            class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                            :class="badgeClass(payment.status)"
                                        >
                                            {{ payment.status_label }}
                                        </span>
                                    </td>
                                    <td class="py-2 text-right">
                                        <div class="flex justify-end gap-2">
                                            <button
                                                v-if="payment.status !== 'zaksiegowana'"
                                                type="button"
                                                class="text-xs font-medium text-green-700 hover:text-green-900"
                                                @click="setPaymentStatus(payment, 'zaksiegowana')"
                                            >
                                                Zaksięguj
                                            </button>
                                            <button
                                                v-if="payment.status === 'zaksiegowana'"
                                                type="button"
                                                class="text-xs font-medium text-amber-700 hover:text-amber-900"
                                                @click="setPaymentStatus(payment, 'oczekuje')"
                                            >
                                                Cofnij do „oczekuje”
                                            </button>
                                            <button
                                                v-if="payment.status !== 'anulowana'"
                                                type="button"
                                                class="text-xs font-medium text-gray-500 hover:text-gray-700"
                                                @click="setPaymentStatus(payment, 'anulowana')"
                                            >
                                                Anuluj
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <p v-else class="mt-3 text-sm text-gray-400">Brak zarejestrowanych płatności.</p>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
