<?php

namespace Database\Seeders;

use App\Models\Lab;
use App\Models\Subject;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $subjects = [
            ['name' => 'PHP with MySQL', 'code' => 'PHP'],
            ['name' => 'Object Oriented Programming with Java', 'code' => 'JAVA'],
            ['name' => 'Financial Management', 'code' => 'FIN_MGMT'],
            ['name' => 'Entrepreneurship', 'code' => 'ENTREPRENEURSHIP'],
            ['name' => 'PHP with MySQL Lab', 'code' => 'PHP_LAB'],
            ['name' => 'Object Oriented Programming with Java Lab', 'code' => 'JAVA_LAB'],
        ];

        foreach ($subjects as $subject) {
            Subject::query()->updateOrCreate(['code' => $subject['code']], ['name' => $subject['name']]);
        }

        foreach (['PHP with MySQL Lab', 'Java Programming Lab'] as $name) {
            Lab::query()->firstOrCreate(['name' => $name]);
        }
    }
}
