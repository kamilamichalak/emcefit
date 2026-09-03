<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    client: { type: Object, required: true },
    regulaminHtml: { type: String, required: true },
    submitUrl: { type: String, required: true },
});

const form = useForm({
    password: '',
    password_confirmation: '',
    terms_accepted: false,
    health_declaration: false,
});

const submit = () => form.post(props.submitUrl);
</script>

<template>
    <Head title="Aktywacja konta" />

    <div class="min-h-screen bg-gray-100 py-10">
        <div class="mx-auto max-w-3xl px-4">
            <div class="mb-6 flex flex-col items-center">
                <ApplicationLogo class="text-4xl text-gray-700" />
                <h1 class="mt-4 text-xl font-semibold text-gray-800">Aktywacja konta</h1>
                <p class="mt-1 text-sm text-gray-600">
                    {{ client.name }} · {{ client.email }}
                </p>
            </div>

            <form
                @submit.prevent="submit"
                class="space-y-6 rounded-lg bg-white p-6 shadow-sm"
            >
                <div>
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">
                        Regulamin zajęć
                    </h2>
                    <div
                        class="regulamin mt-2 max-h-96 overflow-y-auto rounded-md border border-gray-200 bg-gray-50 p-4 text-sm text-gray-700"
                        v-html="regulaminHtml"
                    />
                </div>

                <div class="space-y-3 border-t border-gray-100 pt-4">
                    <label class="flex items-start gap-3">
                        <input
                            type="checkbox"
                            v-model="form.terms_accepted"
                            class="mt-1 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                        />
                        <span class="text-sm text-gray-700">
                            Zapoznałem/am się z regulaminem i akceptuję jego treść.
                        </span>
                    </label>
                    <InputError :message="form.errors.terms_accepted" />

                    <label class="flex items-start gap-3">
                        <input
                            type="checkbox"
                            v-model="form.health_declaration"
                            class="mt-1 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                        />
                        <span class="text-sm text-gray-700">
                            Oświadczam, że nie posiadam przeciwwskazań zdrowotnych do udziału
                            w zajęciach i biorę w nich udział na własną odpowiedzialność.
                        </span>
                    </label>
                    <InputError :message="form.errors.health_declaration" />
                </div>

                <div class="grid gap-6 border-t border-gray-100 pt-4 sm:grid-cols-2">
                    <div>
                        <InputLabel for="password" value="Ustaw hasło" />
                        <TextInput
                            id="password"
                            v-model="form.password"
                            type="password"
                            class="mt-1 block w-full"
                            autocomplete="new-password"
                        />
                        <InputError class="mt-1" :message="form.errors.password" />
                    </div>
                    <div>
                        <InputLabel for="password_confirmation" value="Powtórz hasło" />
                        <TextInput
                            id="password_confirmation"
                            v-model="form.password_confirmation"
                            type="password"
                            class="mt-1 block w-full"
                            autocomplete="new-password"
                        />
                    </div>
                </div>

                <div class="flex justify-end">
                    <PrimaryButton :disabled="form.processing">Aktywuj konto</PrimaryButton>
                </div>
            </form>
        </div>
    </div>
</template>

<style scoped>
.regulamin :deep(h1) {
    font-size: 1rem;
    font-weight: 700;
    margin: 0 0 0.5rem;
}
.regulamin :deep(h2) {
    font-size: 0.9rem;
    font-weight: 700;
    margin: 1rem 0 0.35rem;
    color: #374151;
}
.regulamin :deep(h3) {
    font-size: 0.85rem;
    font-weight: 600;
    margin: 0.75rem 0 0.25rem;
    color: #4b5563;
}
.regulamin :deep(p) {
    margin: 0 0 0.5rem;
}
.regulamin :deep(em) {
    color: #6b7280;
}
</style>
