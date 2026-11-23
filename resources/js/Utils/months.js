// Polskie nazwy miesięcy w odmianie — żeby komunikaty mówiły „w grudniu”, a nie „w tym miesiącu”.
const LOCATIVE = ['styczniu', 'lutym', 'marcu', 'kwietniu', 'maju', 'czerwcu', 'lipcu', 'sierpniu', 'wrześniu', 'październiku', 'listopadzie', 'grudniu'];
const GENITIVE = ['stycznia', 'lutego', 'marca', 'kwietnia', 'maja', 'czerwca', 'lipca', 'sierpnia', 'września', 'października', 'listopada', 'grudnia'];

const toDate = (value) => (value instanceof Date ? value : new Date(value ?? Date.now()));

/** „w grudniu” */
export const inMonth = (value) => `w ${LOCATIVE[toDate(value).getMonth()]}`;

/** „do końca grudnia” */
export const untilEndOf = (value) => `do końca ${GENITIVE[toDate(value).getMonth()]}`;

/** „w grudniu” dla wartości „2026-12” */
export const inMonthKey = (key) => inMonth(`${key}-01T12:00:00`);
