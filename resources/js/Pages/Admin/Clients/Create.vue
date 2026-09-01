<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import ClientFormFields from './Partials/ClientFormFields.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    statuses: { type: Array, default: () => [] },
});

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    phone: '',
    birth_date: '',
    status: props.statuses[0]?.value ?? 'aktywny',
    join_date: '',
    terms_accepted: false,
    health_declaration: false,
});

const submit = () => form.post(route('admin.clients.store'));
</script>

<template>
    <Head title="Nowy klient" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Nowy klient</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
                <form
                    @submit.prevent="submit"
                    class="space-y-6 bg-white p-6 shadow-sm sm:rounded-lg"
                >
                    <ClientFormFields :form="form" :statuses="statuses" mode="create" />

                    <div class="flex items-center gap-4">
                        <PrimaryButton :disabled="form.processing">Zapisz klienta</PrimaryButton>
                        <Link
                            :href="route('admin.clients.index')"
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
