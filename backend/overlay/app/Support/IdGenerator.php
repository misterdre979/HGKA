<?php

namespace App\Support;

use App\Models\IdSequence;
use Illuminate\Support\Facades\DB;

class IdGenerator
{
    /** Atomically increments and returns a named counter. */
    public static function next(string $key): int
    {
        IdSequence::firstOrCreate(['key' => $key], ['value' => 0]);

        return DB::transaction(function () use ($key) {
            $row = IdSequence::where('key', $key)->lockForUpdate()->first();
            $row->value = $row->value + 1;
            $row->save();

            return (int) $row->value;
        });
    }

    public static function studentNo(?int $year = null): string
    {
        $year ??= (int) date('Y');

        return sprintf('HGKA/%d/%04d', $year, self::next("student:$year"));
    }

    public static function staffNo(): string
    {
        return sprintf('HGKA/STF/%04d', self::next('staff'));
    }

    public static function invoiceNo(int $year): string
    {
        return sprintf('HGKA-INV-%d-%06d', $year, self::next("invoice:$year"));
    }

    public static function receiptNo(int $year): string
    {
        return sprintf('HGKA-RCP-%d-%06d', $year, self::next("receipt:$year"));
    }
}
