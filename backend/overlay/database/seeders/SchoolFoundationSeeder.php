<?php

namespace Database\Seeders;

use App\Models\SchoolSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SchoolFoundationSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            'school_name' => 'His Grace Kiddies Academy',
            'school_short_name' => 'HGKA',
            'currency' => 'NGN',
            'currency_symbol' => '₦',
            'timezone' => 'Africa/Lagos',
            'primary_color' => '#102B50',
            'secondary_color' => '#246BCE',
            'accent_color' => '#E5A52B',
        ];
        foreach ($settings as $key => $value) {
            SchoolSetting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        $classes = [
            ['Nursery 1', 'nursery'], ['Nursery 2', 'nursery'],
            ['Primary 1', 'primary'], ['Primary 2', 'primary'], ['Primary 3', 'primary'],
            ['Primary 4', 'primary'], ['Primary 5', 'primary'], ['Primary 6', 'primary'],
            ['JSS 1', 'secondary'], ['JSS 2', 'secondary'], ['JSS 3', 'secondary'],
            ['SS 1', 'secondary'], ['SS 2', 'secondary'], ['SS 3', 'secondary'],
        ];
        foreach ($classes as $i => [$name, $level]) {
            $id = DB::table('school_classes')->updateOrInsert(
                ['name' => $name],
                ['level' => $level, 'sequence' => $i + 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            );
        }

        foreach (DB::table('school_classes')->pluck('id') as $classId) {
            DB::table('class_arms')->updateOrInsert(
                ['school_class_id' => $classId, 'name' => 'A'],
                ['created_at' => now(), 'updated_at' => now()],
            );
        }
    }
}
