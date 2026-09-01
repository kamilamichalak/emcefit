<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import ClassGroupFormFields from './Partials/ClassGroupFormFields.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    month: { type: Object, required: true },
    weekdays: { type: Array, default: () => [] },
    classTypes: { type: Array, default: () => [] },
    trainers: { type: Array, default: () => [] },
    classGroup: { type: Object, required: true },
});

const form = useForm({
    class_type_id: props.classGroup.class_type_id,
    trainer_id: props.classGroup.trainer_id,
    weekday: props.classGroup.weekday,
    start_time: props.classGroup.start_time,
    duration_minutes: props.classGroup.duration_minutes,
    capacity: props.classGroup.capacity,
});

const submit = () => form.put(route('admin.class-groups.update', props.classGroup.id));
</script>

<template>
    <Head title="Edycja zajęć we wzorcu" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Edycja zajęć — wzorzec na <span class="capitalize">{{ month.label }}</span>
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-2xl sm:px-6 lg:px-8">
                <form
                    @submit.prevent="submit"
                    class="space-y-6 bg-white p-6 shadow-sm sm:rounded-lg"
                >
                    <ClassGroupFormFields
                        :form="form"
                        :weekdays="weekdays"
                        :class-types="classTypes"
                        :trainers="trainers"
                        :suggest-capacity-on-type-change="false"
                    />

                    <div class="flex items-center gap-4">
                        <PrimaryButton :disabled="form.processing">Zapisz zmiany</PrimaryButton>
                        <Link
                            :href="route('admin.class-groups.index', { month: month.value })"
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
