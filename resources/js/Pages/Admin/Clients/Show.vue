<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ClassTypeBadge from '@/Components/ClassTypeBadge.vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { iconComponent } from '@/Utils/classTypeIcons';
import { readableTextColor } from '@/Utils/color';

const props = defineProps({
    client: { type: Object, required: true },
    summary: { type: Object, default: () => ({}) },
    memberships: { type: Array, default: () => [] },
    payments: { type: Array, default: () => [] },
    monthTabs: { type: Array, default: () => [] },
    makeupCredits: { type: Array, default: () => [] },
    login: {
        type: Object,
        default: () => ({ configured: false, configured_at: null, activation_link: null }),
    },
});

const currentYearMonth = new Date().toISOString().slice(0, 7);
const activeMonthIdx = ref(
    Math.max(
        0,
        props.monthTabs.findIndex((t) => t.value === currentYearMonth),
    ),
);
const activeTab = computed(() => props.monthTabs[activeMonthIdx.value] ?? null);

const reservationBadge = (status) =>
    ({
        potwierdzona: 'bg-green-100 text-green-800',
        oczekuje_platnosci: 'bg-amber-100 text-amber-800',
        waitlist: 'bg-blue-100 text-blue-800',
        zwolnione: 'bg-red-100 text-red-700',
        odrobiona: 'bg-indigo-100 text-indigo-800',
    })[status] ?? 'bg-gray-100 text-gray-600';

const makeupState = (state) =>
    ({
        available: { class: 'bg-green-100 text-green-800', label: 'Do wykorzystania' },
        used: { class: 'bg-gray-100 text-gray-500', label: 'Wykorzystane' },
        expired: { class: 'bg-red-100 text-red-700', label: 'Wygasłe' },
    })[state] ?? { class: 'bg-gray-100 text-gray-600', label: state };

// Jedno okno na oba typy linków (aktywacja / reset hasła).
const linkModal = ref(null); // { title, description, url }
const copied = ref(false);

const openLinkModal = (title, description, url) => {
    linkModal.value = { title, description, url };
    copied.value = false;
};

const copyLink = async () => {
    try {
        await navigator.clipboard.writeText(linkModal.value.url);
        copied.value = true;
        setTimeout(() => (copied.value = false), 2000);
    } catch {
        copied.value = false;
    }
};

const toggleStatus = () => {
    if (
        props.client.status === 'aktywny' &&
        !confirm('Czy na pewno chcesz dezaktywować tego klienta?')
    ) {
        return;
    }
    router.patch(route('admin.clients.status', props.client.id), {}, { preserveScroll: true });
};

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
    if (confirm('Usunąć ten karnet? Możliwe tylko, gdy nie ma zaksięgowanej płatności.')) {
        router.delete(route('admin.memberships.destroy', membership.id), { preserveScroll: true });
    }
};

const paymentBadge = (status) =>
    ({
        zaksiegowana: 'bg-green-100 text-green-800',
        oczekuje: 'bg-amber-100 text-amber-800',
        anulowana: 'bg-gray-100 text-gray-500',
    })[status] ?? 'bg-gray-100 text-gray-600';

const membershipBadge = (membership) => {
    if (membership.is_paid) return { class: 'bg-green-100 text-green-800', label: 'Opłacony' };
    if (membership.awaiting_payment) return { class: 'bg-amber-100 text-amber-800', label: 'Oczekuje na wpłatę' };
    return { class: 'bg-gray-100 text-gray-600', label: 'Brak płatności' };
};
</script>

<template>
    <Head :title="`Klient: ${client.name}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="flex flex-wrap items-center gap-2 text-xl font-semibold leading-tight text-gray-800">
                    {{ client.name }}
                    <span
                        class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                        :class="client.status === 'aktywny' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'"
                    >
                        {{ client.status_label }}
                    </span>
                </h2>
                <div class="flex items-center gap-3 text-sm">
                    <Link
                        :href="route('admin.clients.enrollment.create', client.id)"
                        class="rounded-md bg-indigo-600 px-3 py-1.5 font-semibold text-white hover:bg-indigo-500"
                    >
                        Zapisz na zajęcia
                    </Link>
                    <button
                        type="button"
                        class="rounded-md border border-gray-300 px-3 py-1.5 font-medium text-gray-700 hover:bg-gray-50"
                        @click="toggleStatus"
                    >
                        {{ client.status === 'aktywny' ? 'Dezaktywuj' : 'Aktywuj' }}
                    </button>
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

                <!-- Podsumowanie -->
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="rounded-lg bg-white p-4 shadow-sm">
                        <div class="text-xs text-gray-500">Aktywny karnet</div>
                        <div class="mt-1 font-semibold text-gray-900">
                            {{ summary.active_membership ? summary.active_membership.type_name : 'brak' }}
                        </div>
                        <div v-if="summary.active_membership?.end_date" class="text-xs text-gray-500">
                            do {{ summary.active_membership.end_date }}
                        </div>
                    </div>
                    <div class="rounded-lg bg-white p-4 shadow-sm">
                        <div class="text-xs text-gray-500">Wpłaty zaksięgowane</div>
                        <div class="mt-1 text-xl font-semibold text-gray-900">
                            {{ money(summary.settled_total ?? 0) }}
                        </div>
                    </div>
                    <div class="rounded-lg bg-white p-4 shadow-sm">
                        <div class="text-xs text-gray-500">Wpłaty oczekujące</div>
                        <div class="mt-1 text-xl font-semibold text-gray-900">
                            {{ money(summary.pending_total ?? 0) }}
                        </div>
                        <div class="text-xs text-gray-500">{{ summary.pending_count ?? 0 }} szt.</div>
                    </div>
                    <div class="rounded-lg bg-white p-4 shadow-sm">
                        <div class="text-xs text-gray-500">Karnety łącznie</div>
                        <div class="mt-1 text-xl font-semibold text-gray-900">
                            {{ summary.memberships_count ?? 0 }}
                        </div>
                    </div>
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

                <!-- Logowanie klienta — informacja, nie status -->
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg bg-white p-6 text-sm shadow-sm">
                    <div>
                        <span class="font-medium text-gray-700">Logowanie klienta:</span>
                        <span v-if="login.configured" class="ml-1 text-gray-700">
                            skonfigurowane (od {{ login.configured_at }})
                        </span>
                        <span v-else class="ml-1 text-amber-700">nie skonfigurowane</span>
                        <p class="mt-0.5 text-xs text-gray-400">
                            Czy klient ma hasło i może się zalogować. To osobna sprawa od statusu —
                            aktywny klient nie musi mieć skonfigurowanego logowania.
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <SecondaryButton
                            v-if="!login.configured"
                            @click="openLinkModal(
                                'Link aktywacyjny',
                                'Wyślij klientowi (np. WhatsApp, Messenger). Ważny 7 dni, jednorazowy — po ustawieniu hasła i akceptacji regulaminu przestaje działać.',
                                login.activation_link,
                            )"
                        >
                            Wygeneruj link aktywacyjny
                        </SecondaryButton>
                        <SecondaryButton
                            v-if="login.configured"
                            @click="openLinkModal(
                                'Link do zresetowania hasła',
                                'Wyślij klientowi. Ważny 24 godziny. Po ustawieniu nowego hasła klient zostanie od razu zalogowany.',
                                login.reset_link,
                            )"
                        >
                            Wygeneruj link do zresetowania hasła
                        </SecondaryButton>
                    </div>
                </div>

                <!-- Karnety -->
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-800">Historia karnetów</h3>
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
                        class="flex flex-wrap items-start justify-between gap-3 rounded-lg bg-white p-5 shadow-sm"
                    >
                        <div>
                            <div class="font-medium text-gray-900">
                                {{ membership.type_name }}
                                <span class="ml-1 text-xs font-normal text-gray-400">({{ membership.mode_label }})</span>
                            </div>
                            <div class="mt-1 text-sm text-gray-600">
                                Okres: {{ membership.start_date || '—' }} → {{ membership.end_date || '—' }}
                                <template v-if="membership.first_entry_date">
                                    · 1. wejście: {{ membership.first_entry_date }}
                                </template>
                                <template v-if="membership.entries_remaining !== null">
                                    · wejścia pozostałe: {{ membership.entries_remaining }}
                                </template>
                                · płatności: {{ membership.payments_count }}
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span
                                class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="membershipBadge(membership).class"
                            >
                                {{ membershipBadge(membership).label }}
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
                </div>

                <!-- Zapisy na zajęcia — zakładki miesięcy -->
                <div class="space-y-3">
                    <h3 class="text-lg font-semibold text-gray-800">Zapisy na zajęcia</h3>

                    <p v-if="monthTabs.length === 0" class="rounded-lg bg-white p-6 text-sm text-gray-500 shadow-sm">
                        Klient nie ma jeszcze żadnych zapisów.
                    </p>

                    <template v-else>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="(tab, idx) in monthTabs"
                                :key="tab.membership_id"
                                type="button"
                                class="rounded-md border px-3 py-1.5 text-sm capitalize"
                                :class="
                                    idx === activeMonthIdx
                                        ? 'border-indigo-500 bg-indigo-50 font-semibold text-indigo-700'
                                        : 'border-gray-300 text-gray-700 hover:bg-gray-50'
                                "
                                @click="activeMonthIdx = idx"
                            >
                                {{ tab.label }}
                            </button>
                        </div>

                        <div v-if="activeTab" class="space-y-4 rounded-lg bg-white p-5 shadow-sm">
                            <!-- Karnet + płatność za ten miesiąc (do odczytu) -->
                            <div class="flex flex-wrap items-start justify-between gap-3 border-b border-gray-100 pb-4">
                                <div>
                                    <div class="text-sm text-gray-500">Karnet</div>
                                    <div class="font-semibold text-gray-900">{{ activeTab.type_name }}</div>
                                    <div class="mt-0.5 text-sm text-gray-500">
                                        {{ money(activeTab.price) }}
                                        <template v-if="activeTab.start_date">
                                            · {{ activeTab.start_date }}<template v-if="activeTab.end_date"> – {{ activeTab.end_date }}</template>
                                        </template>
                                    </div>
                                </div>
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                    :class="paymentBadge(activeTab.payment_status)"
                                >
                                    {{ activeTab.payment_status_label }}
                                </span>
                            </div>

                            <!-- Wybrane zajęcia (co tydzień) -->
                            <div>
                                <div class="text-sm font-medium text-gray-700">Wybrane zajęcia (co tydzień)</div>
                                <ul v-if="activeTab.classes.length" class="mt-2 space-y-1.5">
                                    <li
                                        v-for="(cls, i) in activeTab.classes"
                                        :key="i"
                                        class="flex items-center gap-2 text-sm text-gray-700"
                                    >
                                        <span class="w-24 font-medium">{{ cls.weekday_label }}</span>
                                        <span class="tabular-nums">{{ cls.start_time }}–{{ cls.end_time }}</span>
                                        <span
                                            class="inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-xs"
                                            :style="{
                                                backgroundColor: cls.type_color,
                                                color: readableTextColor(cls.type_color),
                                            }"
                                        >
                                            <component :is="iconComponent(cls.type_icon)" :size="12" :stroke-width="2.5" />
                                            {{ cls.type_name }}
                                        </span>
                                    </li>
                                </ul>
                                <p v-else class="mt-1 text-sm text-gray-400">Brak zajęć cyklicznych w tym karnecie.</p>
                            </div>

                            <!-- Lista terminów -->
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 text-sm">
                                    <thead class="text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                                        <tr>
                                            <th class="py-2 pr-4">Zajęcia</th>
                                            <th class="py-2 pr-4">Termin</th>
                                            <th class="py-2 pr-4">Status</th>
                                            <th class="py-2 pr-4">Zgłoszenie</th>
                                            <th class="py-2 pr-4">Potwierdzenie</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <tr v-for="res in activeTab.reservations" :key="res.id">
                                            <td class="py-2 pr-4">
                                                <span class="inline-flex items-center gap-2 text-gray-700">
                                                    <ClassTypeBadge :color="res.type_color" :icon="res.type_icon" size="sm" />
                                                    {{ res.type_name }}
                                                </span>
                                            </td>
                                            <td class="py-2 pr-4 capitalize text-gray-600">{{ res.date }}, {{ res.start_time }}</td>
                                            <td class="py-2 pr-4">
                                                <span
                                                    class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                                    :class="reservationBadge(res.status)"
                                                >
                                                    {{ res.status_label }}
                                                </span>
                                            </td>
                                            <td class="py-2 pr-4 text-gray-500">{{ res.reported_at || '—' }}</td>
                                            <td class="py-2 pr-4 text-gray-500">{{ res.confirmed_at || '—' }}</td>
                                        </tr>
                                        <tr v-if="activeTab.reservations.length === 0">
                                            <td colspan="5" class="py-8 text-center text-gray-500">
                                                Brak terminów w tym miesiącu.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Zajęcia do odrobienia -->
                <div class="space-y-3">
                    <h3 class="text-lg font-semibold text-gray-800">Zajęcia do odrobienia</h3>

                    <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="px-4 py-2">Za zajęcia</th>
                                    <th class="px-4 py-2">Przyznano</th>
                                    <th class="px-4 py-2">Ważność</th>
                                    <th class="px-4 py-2">Stan</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="credit in makeupCredits" :key="credit.id">
                                    <td class="px-4 py-2 capitalize text-gray-700">
                                        <template v-if="credit.source_type_name">
                                            {{ credit.source_type_name }}<template v-if="credit.source_date"> — {{ credit.source_date }}</template>
                                        </template>
                                        <template v-else>—</template>
                                    </td>
                                    <td class="px-4 py-2 text-gray-600">{{ credit.granted_at || '—' }}</td>
                                    <td class="px-4 py-2 text-gray-500">
                                        {{ credit.expires_end_of_month ? 'do końca miesiąca' : 'bezterminowo' }}
                                    </td>
                                    <td class="px-4 py-2">
                                        <span
                                            class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                            :class="makeupState(credit.state).class"
                                        >
                                            {{ makeupState(credit.state).label }}
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="makeupCredits.length === 0">
                                    <td colspan="4" class="px-4 py-8 text-center text-gray-500">
                                        Brak zajęć do odrobienia.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Historia płatności -->
                <div class="space-y-3">
                    <h3 class="text-lg font-semibold text-gray-800">Historia płatności</h3>

                    <div class="overflow-x-auto rounded-lg bg-white shadow-sm">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                                <tr>
                                    <th class="px-4 py-2">Zgłoszono</th>
                                    <th class="px-4 py-2">Zaksięgowano</th>
                                    <th class="px-4 py-2">Kwota</th>
                                    <th class="px-4 py-2">Karnet</th>
                                    <th class="px-4 py-2">Tytuł przelewu</th>
                                    <th class="px-4 py-2">Status</th>
                                    <th class="px-4 py-2 text-right">Akcje</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="payment in payments" :key="payment.id">
                                    <td class="px-4 py-2 text-gray-600">{{ payment.reported_date || '—' }}</td>
                                    <td class="px-4 py-2 text-gray-600">{{ payment.settled_date || '—' }}</td>
                                    <td class="px-4 py-2 text-gray-700">{{ money(payment.amount) }}</td>
                                    <td class="px-4 py-2 text-gray-600">{{ payment.membership_type_name }}</td>
                                    <td class="px-4 py-2 text-gray-600">{{ payment.transfer_title || '—' }}</td>
                                    <td class="px-4 py-2">
                                        <span
                                            class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                            :class="paymentBadge(payment.status)"
                                        >
                                            {{ payment.status_label }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2 text-right">
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
                                                Cofnij
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
                                <tr v-if="payments.length === 0">
                                    <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                                        Brak zarejestrowanych płatności.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <Modal :show="linkModal !== null" @close="linkModal = null">
            <div v-if="linkModal" class="space-y-4 p-6">
                <h3 class="text-lg font-semibold text-gray-900">{{ linkModal.title }}</h3>
                <p class="text-sm text-gray-600">{{ linkModal.description }}</p>
                <div class="flex gap-2">
                    <input
                        :value="linkModal.url"
                        readonly
                        class="w-full rounded-md border-gray-300 bg-gray-50 text-xs shadow-sm"
                        @focus="$event.target.select()"
                    />
                    <button
                        type="button"
                        class="shrink-0 rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-500"
                        @click="copyLink"
                    >
                        {{ copied ? 'Skopiowano' : 'Kopiuj' }}
                    </button>
                </div>
                <div class="flex justify-end">
                    <SecondaryButton @click="linkModal = null">Zamknij</SecondaryButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
