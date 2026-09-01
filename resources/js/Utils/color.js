/**
 * Zwraca ciemny albo jasny kolor tekstu, zależnie od jasności tła (hex #RRGGBB).
 */
export function readableTextColor(hex) {
    const value = String(hex || '').replace('#', '');
    if (value.length !== 6) return '#1f2937';

    const r = parseInt(value.slice(0, 2), 16);
    const g = parseInt(value.slice(2, 4), 16);
    const b = parseInt(value.slice(4, 6), 16);
    const luminance = 0.299 * r + 0.587 * g + 0.114 * b;

    return luminance > 150 ? '#1f2937' : '#ffffff';
}
