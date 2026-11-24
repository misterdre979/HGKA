<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

class ArrayExport implements FromArray, WithHeadings, ShouldAutoSize, WithTitle
{
    public function __construct(private array $headings, private array $rows, private string $title = 'Report')
    {
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function title(): string
    {
        // Excel sheet names: max 31 chars, none of  \ / ? * [ ] :
        return mb_substr(preg_replace('/[\\\\\/\?\*\[\]:]/', ' ', $this->title), 0, 31);
    }
}
