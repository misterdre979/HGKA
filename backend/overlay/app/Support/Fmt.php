<?php

namespace App\Support;

class Fmt
{
    /** Builds a display label from a row using the resource label columns and optional "{0} {1}" format. */
    public static function label($row, array $cols, ?string $fmt = null): string
    {
        $vals = array_map(function ($c) use ($row) {
            $v = is_array($row) ? ($row[$c] ?? '') : ($row->{$c} ?? '');

            return (string) $v;
        }, $cols);

        if ($fmt) {
            $out = preg_replace_callback('/\{(\d+)\}/', fn ($m) => $vals[(int) $m[1]] ?? '', $fmt);
            $out = preg_replace('/\(\s*\)/', '', $out);

            return trim(preg_replace('/\s+/', ' ', $out));
        }

        return trim(implode(' ', array_filter($vals, fn ($v) => $v !== '')));
    }

    public static function money(float|int|string|null $n): string
    {
        return '₦'.number_format((float) $n, 2);
    }
}
