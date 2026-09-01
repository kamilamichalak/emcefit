<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import ClassTypeFormFields from './Partials/ClassTypeFormFields.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    classType: { type: Object, required: true },
});

const form = useForm({
    name: props.classType.name ?? '',
    description: props.classType.description ?? '',
    required_equipment: props.classType.required_equipment ?? '',
    color: props.classType.color ?? '#E91E63',
    default_capacity: props.classType.default_capacity ?? 20,
});

const submit = () => form.put(route('admin.class-types.update', props.classType.id));
</script>

<template>
    <Head title="Edycja typu zajęć" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Edycja typu zajęć: {{ classType.name }}
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-xl sm:px-6 lg:px-8">
                <form
                    @submit.prevent="submit"
                    class="space-y-6 bg-white p-6 shadow-sm sm:rounded-lg"
                >
                    <ClassTypeFormFields :form="form" />

                    <div class="flex items-center gap-4">
                        <PrimaryButton :disabled="form.processing">Zapisz zmiany</PrimaryButton>
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
