<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    options: { type: Array, default: () => [] },
    preferences: { type: Object, default: () => ({}) },
});

const form = useForm({ preferences: { ...props.preferences } });

const save = () => form.patch(route('client.mail-preferences.update'), { preserveScroll: true });
</script>

<template>
    <Head title="Powiadomienia" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Powiadomienia mailowe</h2>
        </template>

        <div class="px-4 py-6 sm:py-10">
            <div class="mx-auto max-w-xl space-y-4">
                <div v-if="$page.props.flash?.success" class="rounded-md bg-green-50 p-4 text-sm text-green-800">
                    {{ $page.props.flash.success }}
                </div>

                <form class="rounded-lg bg-white p-5 shadow-sm" @submit.prevent="save">
                    <p class="text-sm text-gray-600">Zaznacz, o czym chcesz dostawać maile.</p>

                    <ul class="mt-3 divide-y divide-gray-100">
                        <li v-for="opt in options" :key="opt.key">
                            <label class="flex cursor-pointer items-center gap-3 py-3">
                                <input
                                    v-model="form.preferences[opt.key]"
                                    type="checkbox"
                                    class="h-5 w-5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                />
                                <span class="text-gray-800">{{ opt.label }}</span>
                            </label>
                        </li>
                    </ul>

                    <p class="mt-2 text-xs text-gray-500">
                        Maile potrzebne do korzystania z konta (zaproszenie, zmiana hasła, odpowiedź na zgłoszenie) przychodzą zawsze.
                    </p>

                    <PrimaryButton class="mt-4 w-full justify-center py-3" :disabled="form.processing">
                        Zapisz
                    </PrimaryButton>
                </form>

                <p class="text-center text-sm">
                    <Link :href="route('client.dashboard')" class="text-gray-600 underline">Wróć do panelu</Link>
                </p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
