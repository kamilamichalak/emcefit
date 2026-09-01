<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import ClassTypeFormFields from './Partials/ClassTypeFormFields.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    description: '',
    required_equipment: '',
});

const submit = () => form.post(route('admin.class-types.store'));
</script>

<template>
    <Head title="Nowy typ zajęć" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Nowy typ zajęć</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-xl sm:px-6 lg:px-8">
                <form
                    @submit.prevent="submit"
                    class="space-y-6 bg-white p-6 shadow-sm sm:rounded-lg"
                >
                    <ClassTypeFormFields :form="form" />

                    <div class="flex items-center gap-4">
                        <PrimaryButton :disabled="form.processing">Zapisz</PrimaryButton>
                        <Link
                            :href="route('admin.class-types.index')"
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
