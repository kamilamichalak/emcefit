<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    membership: { type: Object, required: true },
});

const form = useForm({
    amount: Number(props.membership.suggested_amount ?? 0).toFixed(2),
    reported_date: new Date().toISOString().slice(0, 10),
    mark_settled: false,
    settled_date: new Date().toISOString().slice(0, 10),
    transfer_title: '',
});

const submit = () =>
    form.post(route('admin.memberships.payments.store', props.membership.id));
</script>

<template>
    <Head title="Rejestracja płatności" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Rejestracja płatności — {{ membership.client_name }}
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-xl sm:px-6 lg:px-8">
                <form
                    @submit.prevent="submit"
                    class="space-y-6 bg-white p-6 shadow-sm sm:rounded-lg"
                >
                    <p class="rounded-md bg-gray-50 p-3 text-sm text-gray-600">
                        Karnet: <span class="font-medium text-gray-800">{{ membership.type_name }}</span>.
                        Płatność wyłącznie przelewem bankowym — zaznacz „zaksięgowana”
                        dopiero po sprawdzeniu wyciągu.
                    </p>

                    <div>
                        <InputLabel for="amount" value="Kwota (zł)" />
                        <TextInput
                            id="amount"
                            v-model="form.amount"
                            type="number"
                            step="0.01"
                            min="0.01"
                            class="mt-1 block w-full"
                        />
                        <InputError class="mt-1" :message="form.errors.amount" />
                    </div>

                    <div>
                        <InputLabel for="reported_date" value="Data zgłoszenia" />
                        <TextInput
                            id="reported_date"
                            v-model="form.reported_date"
                            type="date"
                            class="mt-1 block w-full"
                        />
                        <InputError class="mt-1" :message="form.errors.reported_date" />
                    </div>

                    <div>
                        <InputLabel for="transfer_title" value="Tytuł przelewu" />
                        <TextInput
                            id="transfer_title"
                            v-model="form.transfer_title"
                            type="text"
                            class="mt-1 block w-full"
                        />
                        <InputError class="mt-1" :message="form.errors.transfer_title" />
                    </div>

                    <div class="space-y-2 border-t border-gray-100 pt-4">
                        <label class="flex items-center gap-2">
                            <input
                                type="checkbox"
                                v-model="form.mark_settled"
                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                            />
                            <span class="text-sm text-gray-700">
                                Od razu oznacz jako zaksięgowaną (wpłata potwierdzona na wyciągu)
                            </span>
                        </label>

                        <div v-if="form.mark_settled">
                            <InputLabel for="settled_date" value="Data zaksięgowania" />
                            <TextInput
                                id="settled_date"
                                v-model="form.settled_date"
                                type="date"
                                class="mt-1 block w-full"
                            />
                            <InputError class="mt-1" :message="form.errors.settled_date" />
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        <PrimaryButton :disabled="form.processing">Zarejestruj płatność</PrimaryButton>
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
