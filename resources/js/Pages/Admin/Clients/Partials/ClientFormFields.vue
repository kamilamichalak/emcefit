<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';

defineProps({
    form: { type: Object, required: true },
    statuses: { type: Array, default: () => [] },
    mode: { type: String, default: 'create' },
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

        <div>
            <InputLabel for="status" value="Status" />
            <select
                id="status"
                v-model="form.status"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            >
                <option v-for="option in statuses" :key="option.value" :value="option.value">
                    {{ option.label }}
                </option>
            </select>
            <InputError class="mt-1" :message="form.errors.status" />
        </div>

        <div>
            <InputLabel for="join_date" value="Data dołączenia" />
            <TextInput
                id="join_date"
                v-model="form.join_date"
                type="date"
                class="mt-1 block w-full"
            />
            <InputError class="mt-1" :message="form.errors.join_date" />
            <p v-if="mode === 'create'" class="mt-1 text-xs text-gray-500">
                Puste = dzisiejsza data.
            </p>
        </div>

        <div>
            <InputLabel
                for="password"
                :value="mode === 'create' ? 'Hasło' : 'Nowe hasło'"
            />
            <TextInput
                id="password"
                v-model="form.password"
                type="password"
                class="mt-1 block w-full"
                autocomplete="new-password"
            />
            <InputError class="mt-1" :message="form.errors.password" />
            <p v-if="mode === 'edit'" class="mt-1 text-xs text-gray-500">
                Zostaw puste, aby nie zmieniać.
            </p>
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

        <div class="space-y-2 border-t border-gray-100 pt-4 sm:col-span-2">
            <label class="flex items-start gap-2">
                <input
                    type="checkbox"
                    v-model="form.terms_accepted"
                    class="mt-1 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                />
                <span class="text-sm text-gray-700">
                    Klient zaakceptował regulamin klubu
                </span>
            </label>
            <label class="flex items-start gap-2">
                <input
                    type="checkbox"
                    v-model="form.health_declaration"
                    class="mt-1 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                />
                <span class="text-sm text-gray-700">
                    Klient złożył oświadczenie o braku przeciwwskazań zdrowotnych
                </span>
            </label>
        </div>
    </div>
</template>
