<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    account: { type: String, required: true },
    memberships: { type: Array, default: () => [] },
    defaultTitle: { type: String, default: '' },
    regulaminHtml: { type: String, default: '' },
});

const money = (value) =>
    new Intl.NumberFormat('pl-PL', { style: 'currency', currency: 'PLN' }).format(Number(value));

const copied = ref(null);
const copy = async (key, text) => {
    try {
        await navigator.clipboard.writeText(text);
        copied.value = key;
        setTimeout(() => (copied.value = null), 2000);
    } catch {
        copied.value = null;
    }
};

const showRegulamin = ref(false);
</script>

<template>
    <Head title="Płatności i regulamin" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Płatności i regulamin</h2>
        </template>

        <div class="px-4 py-6 sm:py-10">
            <div class="mx-auto max-w-xl space-y-4">
                <!-- Dane do przelewu -->
                <section class="rounded-lg bg-white p-5 shadow-sm">
                    <h3 class="text-base font-semibold text-gray-900">Dane do przelewu</h3>
                    <div class="mt-3 text-sm text-gray-500">Numer konta</div>
                    <div class="mt-1 flex items-center justify-between gap-3">
                        <span class="font-mono text-base text-gray-900">{{ account }}</span>
                        <button
                            type="button"
                            class="shrink-0 rounded-md border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50"
                            @click="copy('account', account.replace(/\s/g, ''))"
                        >
                            {{ copied === 'account' ? 'Skopiowano ✓' : 'Kopiuj' }}
                        </button>
                    </div>
                    <p class="mt-3 text-sm text-gray-600">
                        Możesz też zapłacić na miejscu w studiu — gotówką albo BLIK-iem.
                    </p>
                </section>

                <!-- Karnety: ile i do kiedy -->
                <section v-for="m in memberships" :key="m.month_label" class="rounded-lg bg-white p-5 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="text-sm text-gray-500"><span class="capitalize">{{ m.month_label }}</span></div>
                            <div class="font-medium text-gray-900">{{ m.type_name }}</div>
                        </div>
                        <span
                            class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium"
                            :class="m.amount_due > 0 ? 'bg-amber-100 text-amber-800' : 'bg-green-100 text-green-800'"
                        >
                            {{ m.amount_due > 0 ? 'do zapłaty' : 'opłacony' }}
                        </span>
                    </div>
                    <template v-if="m.amount_due > 0">
                        <div class="mt-3 text-2xl font-semibold text-gray-900">{{ money(m.amount_due) }}</div>
                        <div class="text-sm text-gray-600">wpłać do <strong>{{ m.deadline }}</strong></div>
                        <div class="mt-3 text-sm text-gray-500">Tytuł przelewu</div>
                        <div class="mt-1 flex items-center justify-between gap-3">
                            <span class="text-sm text-gray-900">{{ m.title }}</span>
                            <button
                                type="button"
                                class="shrink-0 rounded-md border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50"
                                @click="copy(m.month_label, m.title)"
                            >
                                {{ copied === m.month_label ? 'Skopiowano ✓' : 'Kopiuj' }}
                            </button>
                        </div>
                    </template>
                </section>

                <p v-if="!memberships.length" class="rounded-lg bg-white p-5 text-sm text-gray-600 shadow-sm">
                    Nie masz karnetu na ten ani na następny miesiąc. Tytuł przelewu: {{ defaultTitle }}.
                </p>

                <!-- Regulamin — schowany, rozwijany na żądanie -->
                <section class="rounded-lg bg-white shadow-sm">
                    <button
                        type="button"
                        class="flex w-full items-center justify-between px-5 py-4 text-left"
                        :aria-expanded="showRegulamin"
                        @click="showRegulamin = !showRegulamin"
                    >
                        <span class="text-base font-semibold text-gray-900">Regulamin zajęć</span>
                        <span class="text-sm text-gray-500">{{ showRegulamin ? 'zwiń ▲' : 'pokaż ▼' }}</span>
                    </button>
                    <div v-show="showRegulamin" class="regulamin border-t border-gray-100 px-5 py-4 text-sm text-gray-700" v-html="regulaminHtml" />
                </section>

                <p class="text-center text-sm">
                    <Link :href="route('client.dashboard')" class="text-gray-600 underline">Wróć do panelu</Link>
                </p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.regulamin :deep(h1) { font-size: 1.125rem; font-weight: 600; margin-bottom: 0.5rem; }
.regulamin :deep(h2) { font-size: 1rem; font-weight: 600; margin-top: 1rem; margin-bottom: 0.25rem; }
.regulamin :deep(h3) { font-weight: 600; margin-top: 0.75rem; }
.regulamin :deep(p) { margin-top: 0.5rem; }
.regulamin :deep(ol), .regulamin :deep(ul) { margin-top: 0.5rem; padding-left: 1.25rem; list-style: decimal; }
.regulamin :deep(li) { margin-top: 0.25rem; }
</style>
