<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import ClassGroupFormFields from './Partials/ClassGroupFormFields.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    month: { type: Object, required: true },
    weekdays: { type: Array, default: () => [] },
    defaultWeekday: { type: Number, default: 1 },
    classTypes: { type: Array, default: () => [] },
    trainers: { type: Array, default: () => [] },
});

const firstType = props.classTypes[0];

const form = useForm({
    month: props.month.value,
    class_type_id: firstType?.id ?? null,
    trainer_id: null,
    weekday: props.defaultWeekday,
    start_time: '18:00',
    duration_minutes: 55,
    capacity: firstType?.default_capacity ?? 20,
});

const submit = () => form.post(route('admin.class-groups.store'));
</script>

<template>
    <Head title="Nowe zajęcia we wzorcu" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Nowe zajęcia — wzorzec na <span class="capitalize">{{ month.label }}</span>
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
                    />

                    <div class="flex items-center gap-4">
                        <PrimaryButton :disabled="form.processing">Dodaj do wzorca</PrimaryButton>
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
