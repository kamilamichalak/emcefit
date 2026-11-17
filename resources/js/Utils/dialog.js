import { reactive } from 'vue';

/**
 * Własne okienka zamiast natywnych confirm/prompt/alert przeglądarki.
 * Stan jest globalny; okienko rysuje komponent AppDialog (zamontowany raz w app.js).
 */
export const dialogState = reactive({
    open: false,
    type: 'confirm', // confirm | prompt | alert
    title: '',
    message: '',
    confirmText: 'Tak',
    cancelText: 'Anuluj',
    danger: false,
    placeholder: '',
    required: false,
    value: '',
    resolve: null,
});

const show = (options) =>
    new Promise((resolve) => {
        // poprzednie okienko (gdyby było otwarte) traktujemy jako anulowane
        dialogState.resolve?.(options.type === 'prompt' ? null : false);
        Object.assign(dialogState, {
            title: '',
            message: '',
            confirmText: 'Tak',
            cancelText: 'Anuluj',
            danger: false,
            placeholder: '',
            required: false,
            value: '',
            ...options,
            open: true,
            resolve,
        });
    });

/** @returns {Promise<boolean>} */
export const confirmDialog = (message, options = {}) => show({ type: 'confirm', message, ...options });

/** @returns {Promise<string|null>} null = anulowano */
export const promptDialog = (message, options = {}) =>
    show({ type: 'prompt', message, confirmText: 'OK', ...options });

/** @returns {Promise<boolean>} */
export const alertDialog = (message, options = {}) =>
    show({ type: 'alert', message, confirmText: 'OK', ...options });

export const closeDialog = (result) => {
    const resolve = dialogState.resolve;
    dialogState.open = false;
    dialogState.resolve = null;
    resolve?.(result);
};
