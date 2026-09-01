<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';

defineProps({
    form: { type: Object, required: true },
});
</script>

<template>
    <div class="space-y-6">
        <div>
            <InputLabel for="name" value="Nazwa" />
            <TextInput
                id="name"
                v-model="form.name"
                type="text"
                class="mt-1 block w-full"
                placeholder="np. Body Pump"
            />
            <InputError class="mt-1" :message="form.errors.name" />
        </div>

        <div>
            <InputLabel for="required_equipment" value="Wymagany sprzęt (opcjonalnie)" />
            <TextInput
                id="required_equipment"
                v-model="form.required_equipment"
                type="text"
                class="mt-1 block w-full"
                placeholder='np. "sztangi, ławeczki"'
            />
            <InputError class="mt-1" :message="form.errors.required_equipment" />
            <p class="mt-1 text-xs text-gray-500">Informacyjnie — nie wpływa na rezerwacje.</p>
        </div>

        <div>
            <InputLabel for="description" value="Opis (opcjonalnie)" />
            <textarea
                id="description"
                v-model="form.description"
                rows="3"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            />
            <InputError class="mt-1" :message="form.errors.description" />
        </div>
    </div>
</template>
