<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Department;
use App\Models\Company;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $companies = Company::where('code', '!=', 'SCM-CTR')->get();
        $scmCompany = Company::where('code', 'SCM-CTR')->first();

        // Standard departments for each company
        $standardDepartments = [
            ['name' => 'Engineering', 'code' => 'ENG'],
            ['name' => 'Operations', 'code' => 'OPS'], 
            ['name' => 'Human Resources', 'code' => 'HR'],
            ['name' => 'Finance', 'code' => 'FIN'],
            ['name' => 'Quality Assurance', 'code' => 'QA'],
            ['name' => 'Information Technology', 'code' => 'IT'],
        ];

        // Create departments for each company
        foreach ($companies as $company) {
            foreach ($standardDepartments as $department) {
                Department::firstOrCreate([
                    'company_id' => $company->id,
                    'code' => $department['code']
                ], [
                    'name' => $department['name'],
                    'company_id' => $company->id,
                    'code' => $department['code'],
                ]);
            }
        }

        // Create SCM department (centralized)
        if ($scmCompany) {
            Department::firstOrCreate([
                'company_id' => $scmCompany->id,
                'code' => 'SCM'
            ], [
                'name' => 'Supply Chain Management',
                'company_id' => $scmCompany->id,
                'code' => 'SCM',
            ]);
        }
    }
}