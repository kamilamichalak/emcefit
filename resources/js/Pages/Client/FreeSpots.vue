<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ClassTypeBadge from '@/Components/ClassTypeBadge.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    makeupCredits: { type: Number, default: 0 },
    open: { type: Object, default: null },
    days: { type: Array, default: () => [] },
});

const hasEntitlement = computed(() => props.makeupCredits > 0 || props.open !== null);
const booking = ref(null);

const book = (day, item) => {
    const what = item.kind === 'odrobienie' ? 'odrobienie' : '1 wejście z karnetu';
    const question = item.free <= 0
        ? `Te zajęcia są pełne. Dopisać Cię do listy oczekujących (${item.type_name}, ${day.label.toLowerCase()} o ${item.start_time})?\n` +
          `Gdy ktoś zwolni miejsce, wskoczysz automatycznie i dostaniesz maila — dopiero wtedy wykorzystasz ${what}.`
        : `Zapisać Cię na ${item.type_name}, ${day.label.toLowerCase()} o ${item.start_time}?\n` +
          `Wykorzystasz ${what}.` +
          (item.kind === 'odrobienie' ? ' Jeśli potem zwolnisz to miejsce, odrobienie przepada.' : '');

    if (!confirm(question)) return;

    router.post(route('client.free-spots.store', item.id), {}, {
        preserveScroll: true,
        onStart: () => (booking.value = item.id),
        onFinish: () => (booking.value = null),
    });
};
</script>

<template>
    <Head title="Wolne miejsca" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Wolne miejsca</h2>
        </template>

        <div class="px-4 py-6 sm:py-10">
            <div class="mx-auto max-w-2xl space-y-4">
                <div v-if="$page.props.flash?.success" class="rounded-md bg-green-50 p-4 text-sm text-green-800">
                    {{ $page.props.flash.success }}
                    <Link :href="route('client.classes.index')" class="ml-1 font-medium underline">Moje zajęcia →</Link>
                </div>
                <div v-if="$page.props.flash?.error" class="rounded-md bg-red-50 p-4 text-sm text-red-800">
                    {{ $page.props.flash.error }}
                </div>

                <!-- Co przysługuje -->
                <section class="rounded-lg bg-white p-5 text-sm shadow-sm">
                    <template v-if="hasEntitlement">
                        <p v-if="makeupCredits > 0" class="text-gray-800">
                            Masz <strong>{{ makeupCredits }}</strong>
                            {{ makeupCredits === 1 ? 'zajęcia' : 'zajęć' }} do odrobienia — do końca miesiąca.
                        </p>
                        <p v-if="open" class="text-gray-800" :class="makeupCredits > 0 ? 'mt-1' : ''">
                            {{ open.type_name }}: zostało <strong>{{ open.remaining }}</strong>
                            {{ open.remaining === 1 ? 'wejście' : (open.remaining < 5 ? 'wejścia' : 'wejść') }},
                            ważny do {{ open.valid_to }}.
                        </p>
                        <p class="mt-2 text-gray-500">Wybierz zajęcia z wolnym miejscem i kliknij „Zapisz się”.</p>
                    </template>
                    <template v-else>
                        <p class="text-gray-800">Nie masz teraz zajęć do odrobienia ani wejść do wykorzystania.</p>
                        <p class="mt-1 text-gray-500">
                            Odrobienie dostajesz, gdy zwolnisz miejsce co najmniej godzinę przed zajęciami.
                        </p>
                    </template>
                </section>

                <!-- Lista zajęć z wolnymi miejscami -->
                <template v-if="hasEntitlement">
                    <p v-if="days.length === 0" class="rounded-lg bg-white p-5 text-center text-sm text-gray-500 shadow-sm">
                        Na razie nie ma zajęć z wolnymi miejscami. Zajrzyj później — miejsca zwalniają się na bieżąco.
                    </p>

                    <section v-for="day in days" :key="day.label" class="rounded-lg bg-white shadow-sm">
                        <h3 class="px-5 pt-4 text-sm font-semibold text-gray-700">{{ day.label }}</h3>
                        <ul class="mt-1 divide-y divide-gray-100">
                            <li v-for="item in day.items" :key="item.id" class="flex items-center gap-3 px-5 py-3">
                                <ClassTypeBadge :color="item.type_color" :icon="item.type_icon" />
                                <div class="min-w-0 flex-1">
                                    <div class="font-medium text-gray-900">{{ item.start_time }}–{{ item.end_time }}</div>
                                    <div class="text-sm text-gray-600">
                                        {{ item.type_name }} ·
                                        <span v-if="item.free <= 0" class="text-red-600">
                                            pełne<template v-if="item.waiting"> · czeka: {{ item.waiting }}</template>
                                        </span>
                                        <span v-else :class="item.free <= 2 ? 'text-amber-700' : ''">
                                            {{ item.free === 1 ? 'ostatnie miejsce' : `wolne: ${item.free}` }}
                                        </span>
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    class="shrink-0 rounded-md px-4 py-2 text-sm font-semibold disabled:opacity-50"
                                    :class="item.free <= 0
                                        ? 'border border-indigo-300 text-indigo-700 hover:bg-indigo-50'
                                        : 'bg-indigo-600 text-white hover:bg-indigo-500'"
                                    :disabled="booking !== null"
                                    @click="book(day, item)"
                                >
                                    {{ item.free <= 0 ? 'Dopisz do listy' : 'Zapisz się' }}
                                </button>
                            </li>
                        </ul>
                    </section>
                </template>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
