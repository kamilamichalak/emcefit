<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    client: { type: Object, required: true },
    membershipTypes: { type: Array, default: () => [] },
});

const form = useForm({
    membership_type_id: props.membershipTypes[0]?.id ?? null,
    start_date: new Date().toISOString().slice(0, 10),
    first_entry_date: '',
    end_date: '',
    entries_remaining: '',
});

const selectedType = computed(() =>
    props.membershipTypes.find((type) => type.id === form.membership_type_id),
);

const validityHint = computed(() => {
    const type = selectedType.value;
    if (!type || !type.validity_period_type) {
        return 'Ten typ nie ma zdefiniowanego okresu — datę końca ustala się ręcznie.';
    }
    if (type.validity_period_type === 'miesiac_kalendarzowy') {
        return `Ważność: ${type.validity_period_value} mies. — data końca policzy się od daty startu (można nadpisać).`;
    }
    return `Ważność: ${type.validity_period_value} tyg. od pierwszego wejścia — data końca policzy się po podaniu daty 1. wejścia (można nadpisać).`;
});

const entriesHint = computed(() => {
    const type = selectedType.value;
    if (!type) return '';
    if (type.entry_count) {
        return `Domyślnie: ${type.entry_count} wejść. Puste = wartość domyślna typu.`;
    }
    return 'Ten typ nie limituje wejść. Puste = bez limitu.';
});

const submit = () => form.post(route('admin.clients.memberships.store', props.client.id));
</script>

<template>
    <Head title="Przypisz karnet" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Przypisz karnet — {{ client.name }}
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-2xl sm:px-6 lg:px-8">
                <form
                    @submit.prevent="submit"
                    class="space-y-6 bg-white p-6 shadow-sm sm:rounded-lg"
                >
                    <div>
                        <InputLabel for="membership_type_id" value="Typ karnetu" />
                        <select
                            id="membership_type_id"
                            v-model="form.membership_type_id"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option
                                v-for="type in membershipTypes"
                                :key="type.id"
                                :value="type.id"
                            >
                                {{ type.name }} — {{ Number(type.price).toFixed(2) }} zł
                            </option>
                        </select>
                        <InputError class="mt-1" :message="form.errors.membership_type_id" />
                        <p class="mt-1 text-xs text-gray-500">{{ validityHint }}</p>
                    </div>

                    <div class="grid gap-6 sm:grid-cols-2">
                        <div>
                            <InputLabel for="start_date" value="Data startu" />
                            <TextInput
                                id="start_date"
                                v-model="form.start_date"
                                type="date"
                                class="mt-1 block w-full"
                            />
                            <InputError class="mt-1" :message="form.errors.start_date" />
                        </div>

                        <div>
                            <InputLabel for="first_entry_date" value="Data pierwszego wejścia" />
                            <TextInput
                                id="first_entry_date"
                                v-model="form.first_entry_date"
                                type="date"
                                class="mt-1 block w-full"
                            />
                            <InputError class="mt-1" :message="form.errors.first_entry_date" />
                            <p class="mt-1 text-xs text-gray-500">Opcjonalne — zwykle ustawiane później.</p>
                        </div>

                        <div>
                            <InputLabel for="end_date" value="Data końca (nadpisanie)" />
                            <TextInput
                                id="end_date"
                                v-model="form.end_date"
                                type="date"
                                class="mt-1 block w-full"
                            />
                            <InputError class="mt-1" :message="form.errors.end_date" />
                            <p class="mt-1 text-xs text-gray-500">Puste = policz automatycznie z typu karnetu.</p>
                        </div>

                        <div>
                            <InputLabel for="entries_remaining" value="Wejścia pozostałe (nadpisanie)" />
                            <TextInput
                                id="entries_remaining"
                                v-model="form.entries_remaining"
                                type="number"
                                min="0"
                                class="mt-1 block w-full"
                            />
                            <InputError class="mt-1" :message="form.errors.entries_remaining" />
                            <p class="mt-1 text-xs text-gray-500">{{ entriesHint }}</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        <PrimaryButton :disabled="form.processing">Przypisz karnet</PrimaryButton>
                        <Link
                            :href="route('admin.clients.show', client.id)"
                            class="text-sm text-gray-600 hover:text-gray-900"
                        >
                            Anuluj
                        </Link>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
