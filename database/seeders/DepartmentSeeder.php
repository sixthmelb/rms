<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Department;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            ['name' => 'Engineering', 'code' => 'ENG'],
            ['name' => 'Supply Chain Management', 'code' => 'SCM'],
            ['name' => 'Human Resources', 'code' => 'HR'],
            ['name' => 'Finance', 'code' => 'FIN'],
            ['name' => 'Operations', 'code' => 'OPS'],
            ['name' => 'Quality Assurance', 'code' => 'QA'],
            ['name' => 'Information Technology', 'code' => 'IT'],
        ];

        foreach ($departments as $department) {
            Department::firstOrCreate($department);
        }
    }
}