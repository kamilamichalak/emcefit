<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';

defineProps({
    form: { type: Object, required: true },
});
</script>

<template>
    <div class="grid gap-6 sm:grid-cols-2">
        <div>
            <InputLabel for="name" value="Imię i nazwisko" />
            <TextInput
                id="name"
                v-model="form.name"
                type="text"
                class="mt-1 block w-full"
                autocomplete="name"
            />
            <InputError class="mt-1" :message="form.errors.name" />
        </div>

        <div>
            <InputLabel for="email" value="E-mail" />
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
            <InputLabel for="phone" value="Telefon" />
            <TextInput id="phone" v-model="form.phone" type="text" class="mt-1 block w-full" />
            <InputError class="mt-1" :message="form.errors.phone" />
        </div>

        <div>
            <InputLabel for="birth_date" value="Data urodzenia" />
            <TextInput
                id="birth_date"
                v-model="form.birth_date"
                type="date"
                class="mt-1 block w-full"
            />
            <InputError class="mt-1" :message="form.errors.birth_date" />
        </div>
    </div>
</template>
