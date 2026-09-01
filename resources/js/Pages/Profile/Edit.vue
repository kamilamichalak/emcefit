<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const roles = computed(() => usePage().props.auth?.roles ?? []);
// Konta personelu nie mogą się same usunąć (spec 8a).
const canDeleteOwnAccount = computed(
    () => !roles.value.includes('admin') && !roles.value.includes('trainer'),
);
</script>

<template>
    <Head title="Profile" />

    <AuthenticatedLayout>
        <template #header>
            <h2
                class="text-xl font-semibold leading-tight text-gray-800"
            >
                Profile
            </h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <div
                    class="bg-white p-4 shadow sm:rounded-lg sm:p-8"
                >
                    <UpdateProfileInformationForm
                        :must-verify-email="mustVerifyEmail"
                        :status="status"
                        class="max-w-xl"
                    />
                </div>

                <div
                    class="bg-white p-4 shadow sm:rounded-lg sm:p-8"
                >
                    <UpdatePasswordForm class="max-w-xl" />
                </div>

                <div
                    class="bg-white p-4 shadow sm:rounded-lg sm:p-8"
                >
                    <DeleteUserForm v-if="canDeleteOwnAccount" class="max-w-xl" />
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
