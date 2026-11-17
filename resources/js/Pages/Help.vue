<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    prefill: { type: Object, default: () => ({ name: '', email: '' }) },
    from: { type: String, default: '' },
});

const page = usePage();
const loggedIn = computed(() => Boolean(page.props.auth?.user));
const layout = computed(() => (loggedIn.value ? AuthenticatedLayout : GuestLayout));

const thanks = computed(() => page.props.flash?.success);
const showThanks = ref(Boolean(thanks.value));
watch(thanks, (value) => (showThanks.value = Boolean(value)));

const form = useForm({
    name: props.prefill.name,
    email: props.prefill.email,
    message: '',
    from: props.from,
});

const submit = () =>
    form.post(route('help.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset('message'),
    });
</script>

<template>
    <Head title="Pomoc" />

    <component :is="layout">
        <template v-if="loggedIn" #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Pomoc</h2>
        </template>

        <div :class="loggedIn ? 'px-4 py-6 sm:py-10' : ''">
            <div :class="loggedIn ? 'mx-auto max-w-xl rounded-lg bg-white p-5 shadow-sm' : ''">
                <div v-if="showThanks" class="space-y-3 py-2 text-center">
                    <p class="text-lg font-medium text-gray-800">{{ thanks }}</p>
                    <Link
                        :href="loggedIn ? route('client.dashboard') : route('login')"
                        class="inline-block text-sm text-gray-600 underline"
                    >
                        {{ loggedIn ? 'Wróć do panelu' : 'Wróć do logowania' }}
                    </Link>
                </div>

                <template v-else>
                    <h1 v-if="!loggedIn" class="text-lg font-semibold text-gray-800">Pomoc</h1>
                    <p class="mb-4 text-sm text-gray-600">
                        Coś nie działa, czegoś nie rozumiesz albo masz pomysł, co poprawić? Napisz —
                        odpowiemy mailem.
                    </p>

                    <form class="space-y-4" @submit.prevent="submit">
                        <div>
                            <InputLabel for="name" value="Imię i nazwisko" />
                            <TextInput id="name" v-model="form.name" type="text" class="mt-1 block w-full" autocomplete="name" />
                            <InputError class="mt-1" :message="form.errors.name" />
                        </div>

                        <div>
                            <InputLabel for="email" value="Twój e-mail (na niego odpowiemy)" />
                            <TextInput id="email" v-model="form.email" type="email" class="mt-1 block w-full" autocomplete="email" />
                            <InputError class="mt-1" :message="form.errors.email" />
                        </div>

                        <div>
                            <InputLabel for="message" value="W czym możemy pomóc?" />
                            <textarea
                                id="message"
                                v-model="form.message"
                                rows="5"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                placeholder="Np. nie mogę się zalogować / nie widzę swoich zajęć / chciałabym…"
                            />
                            <InputError class="mt-1" :message="form.errors.message" />
                        </div>

                        <PrimaryButton class="w-full justify-center py-3" :disabled="form.processing">
                            Wyślij
                        </PrimaryButton>
                    </form>

                    <p v-if="!loggedIn" class="mt-4 text-center text-sm">
                        <Link :href="route('login')" class="text-gray-600 underline">Wróć do logowania</Link>
                    </p>
                </template>
            </div>
        </div>
    </component>
</template>
