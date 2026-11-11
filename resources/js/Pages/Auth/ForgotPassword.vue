<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    status: { type: String, default: null },
});

const form = useForm({
    email: '',
});

const submit = () => form.post(route('password.email'), { preserveScroll: true });
</script>

<template>
    <GuestLayout>
        <Head title="Nie pamiętam hasła" />

        <h1 class="text-lg font-semibold text-gray-800">Nie pamiętasz hasła?</h1>
        <p class="mt-1 mb-4 text-sm text-gray-600">
            Wpisz adres e-mail, którego używasz w studiu. Wyślemy Ci link do ustawienia nowego hasła.
        </p>

        <div v-if="status" class="mb-4 rounded-md bg-green-50 p-3 text-sm text-green-800">
            {{ status }}
        </div>

        <form @submit.prevent="submit">
            <div>
                <InputLabel for="email" value="E-mail" />
                <TextInput
                    id="email"
                    v-model="form.email"
                    type="email"
                    class="mt-1 block w-full"
                    required
                    autofocus
                    autocomplete="username"
                />
                <InputError class="mt-2" :message="form.errors.email" />
            </div>

            <div class="mt-4 flex items-center justify-between">
                <Link :href="route('login')" class="text-sm text-gray-600 underline hover:text-gray-900">
                    Wróć do logowania
                </Link>
                <PrimaryButton :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
                    Wyślij link
                </PrimaryButton>
            </div>
        </form>
    </GuestLayout>
</template>
