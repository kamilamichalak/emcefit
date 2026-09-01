<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { watch } from 'vue';

const props = defineProps({
    form: { type: Object, required: true },
    weekdays: { type: Array, default: () => [] },
    classTypes: { type: Array, default: () => [] },
    trainers: { type: Array, default: () => [] },
    // Gdy true, zmiana typu zajęć podpowiada domyślny limit miejsc.
    suggestCapacityOnTypeChange: { type: Boolean, default: true },
});

watch(
    () => props.form.class_type_id,
    (id) => {
        if (!props.suggestCapacityOnTypeChange) return;
        const type = props.classTypes.find((t) => t.id === id);
        if (type) props.form.capacity = type.default_capacity;
    },
);
</script>

<template>
    <div class="space-y-6">
        <div class="grid gap-6 sm:grid-cols-2">
            <div>
                <InputLabel for="class_type_id" value="Typ zajęć" />
                <select
                    id="class_type_id"
                    v-model="form.class_type_id"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
                    <option v-for="type in classTypes" :key="type.id" :value="type.id">
                        {{ type.name }}
                    </option>
                </select>
                <InputError class="mt-1" :message="form.errors.class_type_id" />
            </div>

            <div>
                <InputLabel for="trainer_id" value="Trener (opcjonalnie)" />
                <select
                    id="trainer_id"
                    v-model="form.trainer_id"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
                    <option :value="null">— nieprzypisany —</option>
                    <option v-for="trainer in trainers" :key="trainer.id" :value="trainer.id">
                        {{ trainer.name }}
                    </option>
                </select>
                <InputError class="mt-1" :message="form.errors.trainer_id" />
                <p v-if="trainers.length === 0" class="mt-1 text-xs text-gray-500">
                    Brak trenerów w systemie — przypiszesz później.
                </p>
            </div>

            <div>
                <InputLabel for="weekday" value="Dzień tygodnia" />
                <select
                    id="weekday"
                    v-model="form.weekday"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
                    <option v-for="day in weekdays" :key="day.value" :value="day.value">
                        {{ day.label }}
                    </option>
                </select>
                <InputError class="mt-1" :message="form.errors.weekday" />
            </div>

            <div>
                <InputLabel for="start_time" value="Godzina rozpoczęcia" />
                <TextInput
                    id="start_time"
                    v-model="form.start_time"
                    type="time"
                    class="mt-1 block w-full"
                />
                <InputError class="mt-1" :message="form.errors.start_time" />
            </div>

            <div>
                <InputLabel for="duration_minutes" value="Czas trwania (min)" />
                <TextInput
                    id="duration_minutes"
                    v-model="form.duration_minutes"
                    type="number"
                    min="15"
                    max="240"
                    class="mt-1 block w-full"
                />
                <InputError class="mt-1" :message="form.errors.duration_minutes" />
                <p class="mt-1 text-xs text-gray-500">Domyślnie 55 min.</p>
            </div>

            <div>
                <InputLabel for="capacity" value="Limit miejsc" />
                <TextInput
                    id="capacity"
                    v-model="form.capacity"
                    type="number"
                    min="1"
                    max="200"
                    class="mt-1 block w-full"
                />
                <InputError class="mt-1" :message="form.errors.capacity" />
                <p class="mt-1 text-xs text-gray-500">
                    Podpowiadany z typu zajęć — możesz zmienić dla tych konkretnych zajęć.
                </p>
            </div>
        </div>
    </div>
</template>
