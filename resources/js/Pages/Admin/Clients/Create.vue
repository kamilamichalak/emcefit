<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import ClientFormFields from './Partials/ClientFormFields.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    email: '',
    phone: '',
    birth_date: '',
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
                    <p class="text-sm text-gray-500">
                        Tylko dane podstawowe. Klient sam ustawi hasło i zaakceptuje regulamin
                        przez link aktywacyjny — wygenerujesz go na jego karcie po zapisaniu.
                    </p>

                    <ClientFormFields :form="form" />

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
