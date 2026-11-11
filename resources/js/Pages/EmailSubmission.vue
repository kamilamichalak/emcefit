<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const page = usePage();
const thanks = computed(() => page.props.flash?.success);
const showThanks = ref(Boolean(thanks.value));
watch(thanks, (value) => (showThanks.value = Boolean(value)));

const form = useForm({
    full_name: '',
    email: '',
    consent: false,
});

const submit = () =>
    form.post(route('email-submission.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
</script>

<template>
    <Head title="Twoje dane kontaktowe" />

    <div class="min-h-screen bg-gray-100 py-10">
        <div class="mx-auto max-w-md px-4">
            <div class="mb-6 flex flex-col items-center text-center">
                <ApplicationLogo class="text-4xl text-gray-700" />
                <h1 class="mt-4 text-xl font-semibold text-gray-800">Podaj swój adres e-mail</h1>
                <p class="mt-1 text-sm text-gray-600">
                    Przygotowujemy nowy system zapisów na zajęcia. Na ten adres za kilka dni wyślemy Ci zaproszenie.
                </p>
            </div>

            <div v-if="showThanks" class="space-y-4 rounded-lg bg-white p-6 text-center shadow-sm">
                <p class="text-lg font-medium text-gray-800">{{ thanks }}</p>
                <p class="text-sm text-gray-600">
                    Zaproszenie do systemu zapisów wyślemy za kilka dni. Do zobaczenia na zajęciach!
                </p>
                <button
                    type="button"
                    class="text-sm text-gray-500 underline hover:text-gray-700"
                    @click="showThanks = false"
                >
                    Wpisz dane kolejnej osoby
                </button>
            </div>

            <form v-else @submit.prevent="submit" class="space-y-5 rounded-lg bg-white p-6 shadow-sm">
                <div>
                    <InputLabel for="full_name" value="Imię i nazwisko" />
                    <TextInput
                        id="full_name"
                        v-model="form.full_name"
                        type="text"
                        class="mt-1 block w-full"
                        autocomplete="name"
                        autofocus
                    />
                    <InputError class="mt-1" :message="form.errors.full_name" />
                </div>

                <div>
                    <InputLabel for="email" value="Adres e-mail" />
                    <TextInput
                        id="email"
                        v-model="form.email"
                        type="email"
                        class="mt-1 block w-full"
                        autocomplete="email"
                    />
                    <InputError class="mt-1" :message="form.errors.email" />
                </div>

                <div>
                    <label class="flex items-start gap-2">
                        <Checkbox v-model:checked="form.consent" class="mt-0.5" />
                        <span class="text-sm text-gray-600">
                            Wyrażam zgodę na przetwarzanie moich danych (imię, nazwisko, adres e-mail)
                            przez Studio eMCeFit w celu założenia konta w systemie zapisów na zajęcia
                            i kontaktu w sprawie zajęć. Zgodę mogę w każdej chwili wycofać.
                        </span>
                    </label>
                    <InputError class="mt-1" :message="form.errors.consent" />
                </div>

                <PrimaryButton class="w-full justify-center" :disabled="form.processing">
                    Wyślij
                </PrimaryButton>
            </form>
        </div>
    </div>
</template>
