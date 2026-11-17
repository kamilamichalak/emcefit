<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
    memberships: {
        type: Array,
        default: () => [],
    },
});

const roles = computed(() => usePage().props.auth?.roles ?? []);
// Konta personelu nie mogą się same usunąć (spec 8a).
const isClient = computed(() => roles.value.includes('client'));
const canDeleteOwnAccount = computed(
    () => !roles.value.includes('admin') && !roles.value.includes('trainer'),
);
</script>

<template>
    <Head title="Profil" />

    <AuthenticatedLayout>
        <template #header>
            <h2
                class="text-xl font-semibold leading-tight text-gray-800"
            >
                Profil
            </h2>
        </template>

        <div class="px-4 py-6 sm:py-10">
            <div class="mx-auto max-w-2xl space-y-4">
                <!-- Karnet (klientki) -->
                <section v-if="isClient" class="rounded-lg bg-white p-5 text-sm shadow-sm">
                    <h3 class="text-lg font-medium text-gray-900">Twój karnet</h3>
                    <p v-if="!memberships.length" class="mt-2 text-gray-500">Nie masz karnetu na ten ani na następny miesiąc.</p>
                    <div v-for="m in memberships" :key="m.month_label" class="mt-2 flex items-center justify-between gap-3">
                        <span class="text-gray-700"><span class="capitalize">{{ m.month_label }}</span>: {{ m.type_name }}</span>
                        <span
                            class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium"
                            :class="m.paid ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800'"
                        >
                            {{ m.payment_label }}
                        </span>
                    </div>
                </section>

                <!-- Powiadomienia (klientki) -->
                <Link
                    v-if="isClient"
                    :href="route('client.mail-preferences.edit')"
                    class="flex items-center justify-between rounded-lg bg-white p-5 shadow-sm hover:bg-gray-50"
                >
                    <span>
                        <span class="block text-lg font-medium text-gray-900">Powiadomienia mailowe</span>
                        <span class="text-sm text-gray-600">Wybierz, o czym chcesz dostawać maile</span>
                    </span>
                    <span class="text-indigo-700">→</span>
                </Link>

                <div
                    class="rounded-lg bg-white p-5 shadow-sm sm:p-8"
                >
                    <UpdateProfileInformationForm
                        :must-verify-email="mustVerifyEmail"
                        :status="status"
                        class="max-w-xl"
                    />
                </div>

                <div
                    class="rounded-lg bg-white p-5 shadow-sm sm:p-8"
                >
                    <UpdatePasswordForm class="max-w-xl" />
                </div>

                <div
                    class="rounded-lg bg-white p-5 shadow-sm sm:p-8"
                >
                    <section v-if="isClient" class="max-w-xl">
                        <header>
                            <h2 class="text-lg font-medium text-gray-900">Usuwanie konta</h2>
                        </header>
                        <p class="mt-1 text-sm text-gray-600">
                            Chcesz usunąć konto? Napisz do nas przez
                            <Link :href="route('help.create')" class="underline">Pomoc</Link> — zajmiemy się tym
                            i rozliczymy karnet.
                        </p>
                    </section>
                    <DeleteUserForm v-else-if="canDeleteOwnAccount" class="max-w-xl" />
                    <section v-else class="max-w-xl">
                        <header>
                            <h2 class="text-lg font-medium text-gray-900">Usuwanie konta</h2>
                        </header>
                        <p class="mt-1 text-sm text-gray-600">
                            Konta administratora i trenera nie można usunąć z tego ekranu.
                            Jeśli usunięcie konta personelu jest potrzebne, zajmie się tym
                            administrator w osobnym, świadomym trybie.
                        </p>
                    </section>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
