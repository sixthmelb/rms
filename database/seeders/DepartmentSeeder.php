<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Department;
use App\Models\Company;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $akmCompany = Company::where('code', 'AKM')->first();

        // Department for AKM Company
        $departments = [
            ['name' => 'HCDGA-IT', 'code' => 'IT'],
        ];

        // Create departments for AKM company
        foreach ($departments as $department) {
            Department::firstOrCreate([
                'company_id' => $akmCompany->id,
                'code' => $department['code']
            ], [
                'name' => $department['name'],
                'company_id' => $akmCompany->id,
                'code' => $department['code'],
            ]);
        }
    }
}