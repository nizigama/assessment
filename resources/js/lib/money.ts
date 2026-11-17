/**
 * Money arrives from the server as integer nanodollars (1 = $0.000000001).
 * These helpers only format it for display; no money maths happens in floats.
 */
const PER_DOLLAR = 1_000_000_000;

/** 42_318_750 -> "$0.0423" (rounded for reading). */
export function formatUsd(nanos: number, decimals = 4): string {
    const sign = nanos < 0 ? '-' : '';

    return `${sign}$${(Math.abs(nanos) / PER_DOLLAR).toFixed(decimals)}`;
}

/** 42_318_750 -> "$0.042318750" (exact, for tooltips). */
export function exactUsd(nanos: number): string {
    const sign = nanos < 0 ? '-' : '';
    const abs = Math.abs(nanos);
    const fraction = String(abs % PER_DOLLAR).padStart(9, '0');

    return `${sign}$${Math.floor(abs / PER_DOLLAR)}.${fraction}`;
}

/** "12.50" -> 12_500_000_000. Mirrors App\Support\Nanodollars::fromDecimalString. */
export function parseUsd(dollars: string): number {
    const [whole, fraction = ''] = dollars.split('.');

    return (
        Number(whole) * PER_DOLLAR + Number(fraction.padEnd(9, '0').slice(0, 9))
    );
}
