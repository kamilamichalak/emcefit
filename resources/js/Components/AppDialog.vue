<script setup>
import { closeDialog, dialogState as d } from '@/Utils/dialog';
import { nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

const input = ref(null);
const confirmButton = ref(null);
const error = ref('');

watch(
    () => d.open,
    async (open) => {
        document.body.style.overflow = open ? 'hidden' : '';
        error.value = '';
        if (open) {
            await nextTick();
            (d.type === 'prompt' ? input.value : confirmButton.value)?.focus();
        }
    },
);

const cancel = () => closeDialog(d.type === 'prompt' ? null : false);

const accept = () => {
    if (d.type === 'prompt') {
        const value = d.value.trim();
        if (d.required && !value) {
            error.value = 'To pole jest wymagane.';
            return;
        }
        closeDialog(value);
        return;
    }
    closeDialog(true);
};

const onKey = (e) => {
    if (d.open && e.key === 'Escape') {
        e.preventDefault();
        cancel();
    }
};
onMounted(() => document.addEventListener('keydown', onKey));
onUnmounted(() => document.removeEventListener('keydown', onKey));
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="ease-out duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="ease-in duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="d.open"
                class="fixed inset-0 z-[100] flex items-center justify-center bg-gray-900/50 px-4"
                @click.self="cancel"
            >
                <div
                    role="dialog"
                    aria-modal="true"
                    class="w-full max-w-sm rounded-xl bg-white p-5 shadow-xl"
                >
                    <h2 v-if="d.title" class="text-lg font-semibold text-gray-900">{{ d.title }}</h2>
                    <p class="whitespace-pre-line text-base text-gray-700" :class="d.title ? 'mt-2' : ''">{{ d.message }}</p>

                    <form v-if="d.type === 'prompt'" class="mt-3" @submit.prevent="accept">
                        <input
                            ref="input"
                            v-model="d.value"
                            type="text"
                            :placeholder="d.placeholder"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        <p v-if="error" class="mt-1 text-sm text-red-600">{{ error }}</p>
                    </form>

                    <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <button
                            v-if="d.type !== 'alert'"
                            type="button"
                            class="rounded-md border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50"
                            @click="cancel"
                        >
                            {{ d.cancelText }}
                        </button>
                        <button
                            ref="confirmButton"
                            type="button"
                            class="rounded-md px-4 py-2.5 text-sm font-semibold text-white"
                            :class="d.danger ? 'bg-red-600 hover:bg-red-500' : 'bg-indigo-600 hover:bg-indigo-500'"
                            @click="accept"
                        >
                            {{ d.confirmText }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
