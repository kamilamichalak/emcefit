<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    client: { type: Object, required: true },
    submitUrl: { type: String, required: true },
});

const form = useForm({
    password: '',
    password_confirmation: '',
});

const submit = () =>
    form.post(props.submitUrl, {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
</script>

<template>
    <Head title="Reset hasła" />

    <div class="min-h-screen bg-gray-100 py-10">
        <div class="mx-auto max-w-md px-4">
            <div class="mb-6 flex flex-col items-center">
                <ApplicationLogo class="text-4xl text-gray-700" />
                <h1 class="mt-4 text-xl font-semibold text-gray-800">Ustaw nowe hasło</h1>
                <p class="mt-1 text-sm text-gray-600">{{ client.name }} · {{ client.email }}</p>
            </div>

            <form @submit.prevent="submit" class="space-y-5 rounded-lg bg-white p-6 shadow-sm">
                <div>
                    <InputLabel for="password" value="Nowe hasło" />
                    <TextInput
                        id="password"
                        v-model="form.password"
                        type="password"
                        class="mt-1 block w-full"
                        autocomplete="new-password"
                        autofocus
                    />
                    <InputError class="mt-1" :message="form.errors.password" />
                </div>

                <div>
                    <InputLabel for="password_confirmation" value="Powtórz nowe hasło" />
                    <TextInput
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        type="password"
                        class="mt-1 block w-full"
                        autocomplete="new-password"
                    />
                    <InputError class="mt-1" :message="form.errors.password_confirmation" />
                </div>

                <PrimaryButton class="w-full justify-center" :disabled="form.processing">
                    Zapisz hasło i zaloguj
                </PrimaryButton>
            </form>
        </div>
    </div>
</template>
