import { router } from '@inertiajs/vue3';
import { confirmDialog } from '@/Utils/dialog';
import { inMonth } from '@/Utils/months';
import { onMounted, onUnmounted, ref } from 'vue';

const GRACE_MINUTES = 60;

/**
 * Zwalnianie miejsca i wypisywanie z listy oczekujących — wspólne dla panelu i „Moich zajęć”.
 * „Teraz” odświeżane co minutę, żeby okno 1 h było aktualne bez przeładowania.
 */
export function useReleaseReservation() {
    const now = ref(Date.now());
    let ticker = null;
    onMounted(() => {
        ticker = setInterval(() => (now.value = Date.now()), 60_000);
    });
    onUnmounted(() => clearInterval(ticker));

    const withinGracePeriod = (res) =>
        new Date(res.starts_at).getTime() - now.value >= GRACE_MINUTES * 60_000;

    const isLateMakeup = (res) => res.kind === 'odrobienie' && !withinGracePeriod(res);

    const releaseLabel = (res) =>
        isLateMakeup(res) ? 'Rezygnuję' : 'Zwalniam';

    const releaseButtonClass = (res) =>
        isLateMakeup(res)
            ? 'border-amber-300 bg-amber-50 text-amber-800 hover:bg-amber-100'
            : 'border-red-200 text-red-700 hover:bg-red-50';

    const cancelReservation = async (res) => {
        const inGrace = withinGracePeriod(res);
        let question;

        if (res.kind === 'odrobienie') {
            question = inGrace
                ? `Zwolnić miejsce na te zajęcia odrabiane? Odrobienie wróci i wybierzesz inny termin ${inMonth(res.starts_at)}.`
                : 'UWAGA: do zajęć ODRABIANYCH' + (res.makeup_for ? ` (za ${res.makeup_for})` : '') + ' została mniej niż godzina.\n\n' +
                  'Jeśli teraz zwolnisz miejsce, odrobienie przepadnie i nie odrobisz już tamtych zajęć.\n\n' +
                  'Na pewno zwolnić?';
        } else if (res.kind === 'otwarty') {
            question = inGrace
                ? 'Zwolnić miejsce? Wejście wróci na Twój karnet.'
                : 'Do zajęć została mniej niż godzina — po zwolnieniu wejście przepada. Zwolnić mimo to?';
        } else {
            question = inGrace
                ? `Zwolnić miejsce na te zajęcia? Dostaniesz odrobienie do wykorzystania ${inMonth(res.starts_at)}.`
                : 'Zgodnie z regulaminem, zwolnienie miejsca później niż godzinę przed zajęciami NIE daje prawa do odrobienia. Zwolnić mimo to?';
        }

        const ok = await confirmDialog(question, {
            confirmText: 'Zwalniam',
            cancelText: 'Nie, zostaję',
            danger: !inGrace,
        });
        if (!ok) return;

        router.patch(
            route('client.reservations.cancel', res.id),
            inGrace ? {} : { acknowledge_late: true },
            { preserveScroll: true },
        );
    };

    const leaveWaitlist = async (res) => {
        if (!(await confirmDialog('Wypisać Cię z listy oczekujących na te zajęcia?', { confirmText: 'Wypisz mnie' }))) return;
        router.patch(route('client.reservations.cancel', res.id), {}, { preserveScroll: true });
    };

    return { withinGracePeriod, isLateMakeup, releaseLabel, releaseButtonClass, cancelReservation, leaveWaitlist };
}
