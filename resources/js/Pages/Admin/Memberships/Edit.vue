<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

const props = defineProps({
    membership: { type: Object, required: true },
    membershipTypes: { type: Array, default: () => [] },
});

const form = useForm({
    membership_type_id: props.membership.type_id,
    price: props.membership.price_locked,
    note: props.membership.admin_note ?? '',
});

const selectedType = computed(() =>
    props.membershipTypes.find((type) => type.id === form.membership_type_id),
);

// Zmiana typu podstawia jego cennikową cenę — admin może ją potem nadpisać (rabat).
watch(
    () => form.membership_type_id,
    (id) => {
        const type = props.membershipTypes.find((t) => t.id === id);
        if (type) form.price = type.price;
    },
);

const sessionsMismatch = computed(() => {
    const s = selectedType.value?.sessions_per_week;
    return s != null && s !== props.membership.class_groups_count;
});

const submit = () => form.patch(route('admin.memberships.update', props.membership.id));
</script>

<template>
    <Head title="Zmień karnet" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Zmień karnet — {{ membership.client_name }}
                <span class="ml-1 text-sm font-normal capitalize text-gray-500">({{ membership.month_label }})</span>
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-2xl sm:px-6 lg:px-8">
                <form @submit.prevent="submit" class="space-y-6 bg-white p-6 shadow-sm sm:rounded-lg">
                    <p class="text-sm text-gray-500">
                        Obecnie: <span class="font-medium text-gray-700">{{ membership.type_name }}</span>,
                        {{ Number(membership.price_locked).toFixed(2) }} zł.
                        Zmiana nie rusza już zarejestrowanych płatności — ewentualną różnicę rozliczasz ręcznie.
                    </p>

                    <div>
                        <InputLabel for="membership_type_id" value="Nowy typ karnetu" />
                        <select
                            id="membership_type_id"
                            v-model="form.membership_type_id"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option v-for="type in membershipTypes" :key="type.id" :value="type.id">
                                {{ type.name }} — {{ Number(type.price).toFixed(2) }} zł
                            </option>
                        </select>
                        <InputError class="mt-1" :message="form.errors.membership_type_id" />
                    </div>

                    <div>
                        <InputLabel for="price" value="Cena ustalona (zł)" />
                        <TextInput
                            id="price"
                            v-model="form.price"
                            type="number"
                            step="0.01"
                            min="0"
                            class="mt-1 block w-40"
                        />
                        <InputError class="mt-1" :message="form.errors.price" />
                        <p class="mt-1 text-xs text-gray-500">
                            Domyślnie cena wybranego typu — nadpisz dla indywidualnego rabatu.
                        </p>
                    </div>

                    <div>
                        <InputLabel for="note" value="Notatka (opcjonalnie)" />
                        <textarea
                            id="note"
                            v-model="form.note"
                            rows="2"
                            maxlength="500"
                            placeholder="np. rabat świąteczny"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        <InputError class="mt-1" :message="form.errors.note" />
                    </div>

                    <div
                        v-if="sessionsMismatch"
                        class="rounded-md bg-amber-50 p-3 text-sm text-amber-800"
                    >
                        Liczba wybranych zajęć ({{ membership.class_groups_count }}) nie zgadza się z nowym
                        typem karnetu ({{ selectedType.sessions_per_week }}/tydzień).
                        <Link
                            :href="route('admin.clients.enrollment.create', membership.client_id)"
                            class="font-medium underline"
                        >
                            Dostosuj zapisy klientki
                        </Link>. Zapis nie jest blokowany — decydujesz sam.
                    </div>

                    <div class="flex items-center gap-4">
                        <PrimaryButton :disabled="form.processing">Zapisz zmianę</PrimaryButton>
                        <Link
                            :href="route('admin.clients.show', membership.client_id)"
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
