<script setup>
import { confirmDialog } from '@/Utils/dialog';
import { Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    data: { type: Object, required: true },
});

const money = (value) =>
    new Intl.NumberFormat('pl-PL', { style: 'currency', currency: 'PLN' }).format(Number(value));

const percent = computed(() =>
    props.data.expected > 0 ? Math.round((props.data.enrolled / props.data.expected) * 100) : 0,
);

const busy = ref(null);

// Tylko jedna lista rozwinięta naraz — rozwinięcie kolejnej zwija poprzednią.
const openKey = ref(null);
const toggle = (key) => (openKey.value = openKey.value === key ? null : key);
const copied = ref(null);

const copyNames = async (key, rows) => {
    try {
        await navigator.clipboard.writeText(rows.map((r) => r.name).join('\n'));
        copied.value = key;
        setTimeout(() => (copied.value = null), 2000);
    } catch {
        copied.value = null;
    }
};

const repeat = async (row) => {
    const ok = await confirmDialog(
        `Zapisać ${row.name} na ${props.data.month.label} tak jak ${props.data.month.previous_in}?\n${row.previous}`,
        { confirmText: 'Zapisz' },
    );
    if (!ok) return;
    router.post(route('admin.clients.enrollment.repeat', row.id), { month: props.data.month.value }, {
        preserveScroll: true,
        onStart: () => (busy.value = `r${row.id}`),
        onFinish: () => (busy.value = null),
    });
};

const settle = async (row) => {
    const ok = await confirmDialog(
        `Zaksięgować wpłatę ${money(row.amount_due)} od ${row.name}?\nZapisy zmienią się na potwierdzone, a klientka dostanie maila.`,
        { confirmText: 'Zaksięguj' },
    );
    if (!ok) return;
    router.post(route('admin.memberships.settle', row.membership_id), {}, {
        preserveScroll: true,
        onStart: () => (busy.value = `s${row.membership_id}`),
        onFinish: () => (busy.value = null),
    });
};

const resend = async (row) => {
    const ok = await confirmDialog(`Wysłać ${row.name} zaproszenie ponownie?`, { confirmText: 'Wyślij' });
    if (!ok) return;
    router.post(route('admin.clients.invitation', row.id), {}, {
        preserveScroll: true,
        onStart: () => (busy.value = `i${row.id}`),
        onFinish: () => (busy.value = null),
    });
};

const groups = computed(() => [
    {
        key: 'noEmail',
        title: 'Bez maila',
        hint: 'Nie dostaną zaproszenia — zapisz je sama albo poproś o maila (emcefit.com/dane).',
        rows: props.data.noEmail,
    },
    {
        key: 'notActivated',
        title: 'Nie aktywowały konta',
        hint: 'Dostały zaproszenie, ale nie kliknęły linku. Może wpadło do Spamu.',
        rows: props.data.notActivated,
    },
    {
        key: 'activeNotEnrolled',
        title: 'Mają konto, ale się nie zapisały',
        hint: 'Wystarczy przypomnieć albo zapisać je jak w poprzednim miesiącu.',
        rows: props.data.activeNotEnrolled,
    },
]);
</script>

<template>
    <section class="rounded-lg bg-white shadow-sm">
        <div class="border-b border-gray-100 px-5 py-4">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h3 class="text-base font-semibold text-gray-800">
                    Zapisy na <span class="capitalize">{{ data.month.label }}</span> — co zostało
                </h3>
                <span class="text-sm text-gray-600">
                    zapisane <strong>{{ data.enrolled }}</strong> z {{ data.expected }} · opłacone <strong>{{ data.paid }}</strong>
                </span>
            </div>
            <div class="mt-2 h-2 overflow-hidden rounded-full bg-gray-100">
                <div class="h-full rounded-full bg-indigo-500" :style="{ width: `${percent}%` }" />
            </div>
        </div>

        <div class="pt-2">
            <!-- Kto się nie zapisał — trzy powody -->
            <div v-for="g in groups" :key="g.key" class="px-5 py-2">
                <button
                    type="button"
                    class="flex w-full items-center justify-between gap-3 rounded-md border-l-4 px-3 py-2.5 text-left"
                    :class="openKey === g.key ? 'border-indigo-500 bg-indigo-50' : 'border-gray-300 bg-gray-50 hover:bg-gray-100'"
                    :aria-expanded="openKey === g.key"
                    @click="toggle(g.key)"
                >
                    <span class="font-semibold text-gray-900">
                        {{ g.title }}
                        <span class="ml-1 rounded-full bg-white px-2 py-0.5 text-xs font-medium text-gray-700 ring-1 ring-gray-200">{{ g.rows.length }}</span>
                    </span>
                    <span class="text-sm text-gray-500">{{ openKey === g.key ? 'zwiń ▲' : 'pokaż ▼' }}</span>
                </button>
                <div v-show="openKey === g.key" class="ml-1 border-l border-gray-100 pl-4">
                <p class="mt-1 text-xs text-gray-500">{{ g.hint }}</p>
                <button
                    v-if="g.rows.length"
                    type="button"
                    class="mt-2 text-xs text-indigo-700 underline"
                    @click="copyNames(g.key, g.rows)"
                >
                    {{ copied === g.key ? 'Skopiowane ✓' : 'Kopiuj imiona (do wiadomości)' }}
                </button>
                <ul class="mt-2 divide-y divide-gray-50">
                    <li v-for="row in g.rows" :key="row.id" class="flex flex-wrap items-center gap-x-3 gap-y-1 py-2 text-sm">
                        <div class="min-w-0 flex-1">
                            <Link :href="route('admin.clients.show', row.id)" class="font-medium text-gray-900 hover:underline">{{ row.name }}</Link>
                            <div class="text-xs text-gray-500">
                                <template v-if="row.previous">{{ data.month.previous_in }}: {{ row.previous }}</template>
                                <template v-else>nowa klientka</template>
                                <template v-if="g.key === 'notActivated' && row.invited_at"> · zaproszenie {{ row.invited_at }}</template>
                                <template v-if="g.key === 'activeNotEnrolled' && row.last_seen"> · ostatnio w systemie {{ row.last_seen }}</template>
                            </div>
                        </div>
                        <div class="flex shrink-0 gap-2">
                            <button
                                v-if="row.can_repeat"
                                type="button"
                                class="rounded-md bg-indigo-600 px-3 py-1 text-xs font-semibold text-white hover:bg-indigo-500 disabled:opacity-50"
                                :disabled="busy !== null"
                                @click="repeat(row)"
                            >
                                Zapisz jak {{ data.month.previous_in }}
                            </button>
                            <Link
                                :href="route('admin.clients.enrollment.create', { client: row.id, month: data.month.value })"
                                class="rounded-md border border-gray-300 px-3 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50"
                            >
                                {{ row.can_repeat ? 'Zapisz inaczej' : 'Zapisz' }}
                            </Link>
                            <button
                                v-if="g.key === 'notActivated'"
                                type="button"
                                class="rounded-md border border-gray-300 px-3 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50"
                                :disabled="busy !== null"
                                @click="resend(row)"
                            >
                                Zaproszenie ponownie
                            </button>
                        </div>
                    </li>
                </ul>
                </div>
            </div>

            <!-- Zapisane, ale bez wpłaty -->
            <div class="px-5 py-2 pb-4">
                <button
                    type="button"
                    class="flex w-full items-center justify-between gap-3 rounded-md border-l-4 px-3 py-2.5 text-left"
                    :class="openKey === 'unpaid' ? 'border-amber-500 bg-amber-50' : 'border-amber-300 bg-gray-50 hover:bg-gray-100'"
                    :aria-expanded="openKey === 'unpaid'"
                    @click="toggle('unpaid')"
                >
                    <span class="font-semibold text-gray-900">
                        Zapisane, bez wpłaty
                        <span class="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">{{ data.unpaid.length }}</span>
                        <span v-if="data.unpaid.length" class="ml-1 text-xs font-normal text-gray-500">razem {{ money(data.unpaidTotal) }}</span>
                    </span>
                    <span class="text-sm text-gray-500">{{ openKey === 'unpaid' ? 'zwiń ▲' : 'pokaż ▼' }}</span>
                </button>
                <div v-show="openKey === 'unpaid'" class="ml-1 border-l border-gray-100 pl-4">
                <p class="mt-1 text-xs text-gray-500">Gdy przelew jest na koncie albo dostałaś gotówkę/BLIK — „Zaksięguj”.</p>
                <button
                    v-if="data.unpaid.length"
                    type="button"
                    class="mt-2 text-xs text-indigo-700 underline"
                    @click="copyNames('unpaid', data.unpaid)"
                >
                    {{ copied === 'unpaid' ? 'Skopiowane ✓' : 'Kopiuj imiona (do wiadomości)' }}
                </button>
                <ul class="mt-2 divide-y divide-gray-50">
                    <li v-for="row in data.unpaid" :key="row.membership_id" class="flex flex-wrap items-center gap-x-3 gap-y-1 py-2 text-sm">
                        <div class="min-w-0 flex-1">
                            <Link :href="route('admin.clients.show', row.id)" class="font-medium text-gray-900 hover:underline">{{ row.name }}</Link>
                            <div class="text-xs text-gray-500">
                                {{ row.type_name }} · zapis {{ row.enrolled_at }}<template v-if="row.partly_paid"> · dopłata</template>
                            </div>
                        </div>
                        <span class="shrink-0 font-medium text-gray-800">{{ money(row.amount_due) }}</span>
                        <button
                            type="button"
                            class="shrink-0 rounded-md bg-green-600 px-3 py-1 text-xs font-semibold text-white hover:bg-green-500 disabled:opacity-50"
                            :disabled="busy !== null"
                            @click="settle(row)"
                        >
                            Zaksięguj
                        </button>
                    </li>
                </ul>
                </div>
            </div>
        </div>
    </section>
</template>
