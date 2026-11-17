import { router } from '@inertiajs/vue3';
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
        isLateMakeup(res) ? 'Rezygnuję z odrabiania — odrobienie przepadnie' : 'Zwalniam miejsce';

    const cancelReservation = (res) => {
        const inGrace = withinGracePeriod(res);
        let question;

        if (res.kind === 'odrobienie') {
            question = inGrace
                ? 'Zwolnić miejsce na te zajęcia odrabiane? Odrobienie wróci i wybierzesz inny termin w tym miesiącu.'
                : 'UWAGA: do zajęć ODRABIANYCH' + (res.makeup_for ? ` (za ${res.makeup_for})` : '') + ' została mniej niż godzina.\n\n' +
                  'Jeśli teraz zwolnisz miejsce, odrobienie przepadnie i nie odrobisz już tamtych zajęć.\n\n' +
                  'Na pewno zwolnić?';
        } else if (res.kind === 'otwarty') {
            question = inGrace
                ? 'Zwolnić miejsce? Wejście wróci na Twój karnet.'
                : 'Do zajęć została mniej niż godzina — po zwolnieniu wejście przepada. Zwolnić mimo to?';
        } else {
            question = inGrace
                ? 'Zwolnić miejsce na te zajęcia? Otrzymasz prawo do odrobienia w tym miesiącu.'
                : 'Zgodnie z regulaminem, zwolnienie miejsca później niż godzinę przed zajęciami NIE daje prawa do odrobienia. Zwolnić mimo to?';
        }

        if (!confirm(question)) return;

        router.patch(
            route('client.reservations.cancel', res.id),
            inGrace ? {} : { acknowledge_late: true },
            { preserveScroll: true },
        );
    };

    const leaveWaitlist = (res) => {
        if (!confirm('Wypisać Cię z listy oczekujących na te zajęcia?')) return;
        router.patch(route('client.reservations.cancel', res.id), {}, { preserveScroll: true });
    };

    return { withinGracePeriod, isLateMakeup, releaseLabel, cancelReservation, leaveWaitlist };
}
