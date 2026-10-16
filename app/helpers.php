<?php

if (! function_exists('money')) {
    /**
     * Format centavos as pesos for display, e.g. 176400 → "₱1,764.00".
     */
    function money(int|float|null $centavos, bool $symbol = true): string
    {
        $centavos = (int) ($centavos ?? 0);
        $formatted = number_format(abs($centavos) / 100, 2);

        return ($centavos < 0 ? '-' : '').($symbol ? '₱' : '').$formatted;
    }
}

if (! function_exists('money_plain')) {
    /**
     * Centavos as a plain decimal string for CSV exports, e.g. 176400 → "1764.00".
     */
    function money_plain(int|float|null $centavos): string
    {
        return number_format(((int) ($centavos ?? 0)) / 100, 2, '.', '');
    }
}

if (! function_exists('kg')) {
    function kg(int|float|null $value): string
    {
        return number_format((float) ($value ?? 0), 2);
    }
}
