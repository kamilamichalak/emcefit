<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import ClientFormFields from './Partials/ClientFormFields.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    client: { type: Object, required: true },
});

const form = useForm({
    name: props.client.name ?? '',
    email: props.client.email ?? '',
    phone: props.client.phone ?? '',
    birth_date: props.client.birth_date ?? '',
});

const submit = () => form.put(route('admin.clients.update', props.client.id));
</script>

<template>
    <Head title="Edycja klienta" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">
                    Edycja klienta: {{ client.name }}
                </h2>
                <Link
                    :href="route('admin.clients.show', client.id)"
                    class="text-sm text-indigo-600 hover:text-indigo-900"
                >
                    Karta klienta →
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-3xl space-y-4 sm:px-6 lg:px-8">
                <form
                    @submit.prevent="submit"
                    class="space-y-6 bg-white p-6 shadow-sm sm:rounded-lg"
                >
                    <ClientFormFields :form="form" />

                    <div class="flex items-center gap-4">
                        <PrimaryButton :disabled="form.processing">Zapisz zmiany</PrimaryButton>
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
