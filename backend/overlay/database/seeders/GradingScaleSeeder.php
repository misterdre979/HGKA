<?php

namespace Database\Seeders;

use App\Models\GradingScaleItem;
use Illuminate\Database\Seeder;

/** Default grading scales. Schools can edit them under Examinations > Grading scale. */
class GradingScaleSeeder extends Seeder
{
    private const SCALES = [
        'secondary' => [
            ['A1', 75, 100, 'Excellent', 1.0], ['B2', 70, 74, 'Very Good', 2.0], ['B3', 65, 69, 'Good', 3.0],
            ['C4', 60, 64, 'Credit', 4.0], ['C5', 55, 59, 'Credit', 5.0], ['C6', 50, 54, 'Credit', 6.0],
            ['D7', 45, 49, 'Pass', 7.0], ['E8', 40, 44, 'Pass', 8.0], ['F9', 0, 39, 'Fail', 9.0],
        ],
        'primary' => [
            ['A', 70, 100, 'Excellent', 5.0], ['B', 60, 69, 'Very Good', 4.0], ['C', 50, 59, 'Good', 3.0],
            ['D', 40, 49, 'Fair', 2.0], ['E', 0, 39, 'Needs improvement', 1.0],
        ],
        'nursery' => [
            ['A', 80, 100, 'Excellent', 4.0], ['B', 60, 79, 'Good', 3.0], ['C', 40, 59, 'Fair', 2.0], ['D', 0, 39, 'Needs support', 1.0],
        ],
    ];

    public function run(): void
    {
        foreach (self::SCALES as $level => $items) {
            foreach ($items as [$grade, $min, $max, $remark, $points]) {
                GradingScaleItem::updateOrCreate(
                    ['level' => $level, 'grade' => $grade],
                    ['min_score' => $min, 'max_score' => $max, 'remark' => $remark, 'points' => $points]
                );
            }
        }
    }
}
