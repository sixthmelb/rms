<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Company;
use App\Models\Department;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Get companies
        $jktCompany = Company::where('code', 'AKM-JKT')->first();
        $sbyCompany = Company::where('code', 'AKM-SBY')->first();
        $bdgCompany = Company::where('code', 'AKM-BDG')->first();
        $scmCompany = Company::where('code', 'SCM-CTR')->first();

        // Create Super Admin (can access all companies)
        $admin = User::firstOrCreate([
            'email' => 'admin@akm.com'
        ], [
            'name' => 'System Administrator',
            'employee_id' => 'ADM001',
            'password' => Hash::make('password'),
            'company_id' => $jktCompany->id, // Default company
            'department_id' => $jktCompany->departments()->where('code', 'IT')->first()->id,
            'position' => 'System Administrator'
        ]);
        $admin->assignRole('admin');

        // Create Central SCM Head
        $scmDept = $scmCompany->departments()->where('code', 'SCM')->first();
        $mandala = User::firstOrCreate([
            'email' => 'mandala@akm.com'
        ], [
            'name' => 'Mandala SCM',
            'employee_id' => 'SCM001',
            'password' => Hash::make('password'),
            'company_id' => $scmCompany->id,
            'department_id' => $scmDept->id,
            'position' => 'Central SCM Head'
        ]);
        $mandala->assignRole('scm_head');

        // =============== JAKARTA COMPANY ===============
        $this->createCompanyUsers($jktCompany, 'JKT');

        // =============== SURABAYA COMPANY ===============  
        $this->createCompanyUsers($sbyCompany, 'SBY');

        // =============== BANDUNG COMPANY ===============
        $this->createCompanyUsers($bdgCompany, 'BDG');
    }

    private function createCompanyUsers(Company $company, string $cityCode): void
    {
        $engineering = $company->departments()->where('code', 'ENG')->first();
        $operations = $company->departments()->where('code', 'OPS')->first();
        $hr = $company->departments()->where('code', 'HR')->first();

        // Create PJO for this company
        $pjo = User::firstOrCreate([
            'email' => "pjo.{$cityCode}@akm.com"
        ], [
            'name' => "PJO {$company->name}",
            'employee_id' => "PJO{$cityCode}001",
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'department_id' => $engineering->id,
            'position' => 'Project Officer'
        ]);
        $pjo->assignRole('pjo');

        // Create Section Heads for each department
        $sectionHeadEng = User::firstOrCreate([
            'email' => "eng.head.{$cityCode}@akm.com"
        ], [
            'name' => "Engineering Head {$cityCode}",
            'employee_id' => "SH{$cityCode}ENG001",
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'department_id' => $engineering->id,
            'position' => 'Engineering Section Head'
        ]);
        $sectionHeadEng->assignRole('section_head');

        $sectionHeadOps = User::firstOrCreate([
            'email' => "ops.head.{$cityCode}@akm.com"
        ], [
            'name' => "Operations Head {$cityCode}",
            'employee_id' => "SH{$cityCode}OPS001", 
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'department_id' => $operations->id,
            'position' => 'Operations Section Head'
        ]);
        $sectionHeadOps->assignRole('section_head');

        // Create regular users
        $user1 = User::firstOrCreate([
            'email' => "engineer.{$cityCode}@akm.com"
        ], [
            'name' => "Engineer {$cityCode}",
            'employee_id' => "ENG{$cityCode}001",
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'department_id' => $engineering->id,
            'position' => 'Engineer'
        ]);
        $user1->assignRole('user');

        $user2 = User::firstOrCreate([
            'email' => "operator.{$cityCode}@akm.com"
        ], [
            'name' => "Operator {$cityCode}",
            'employee_id' => "OPS{$cityCode}001",
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'department_id' => $operations->id,
            'position' => 'Operator'
        ]);
        $user2->assignRole('user');

        $user3 = User::firstOrCreate([
            'email' => "hr.staff.{$cityCode}@akm.com"
        ], [
            'name' => "HR Staff {$cityCode}",
            'employee_id' => "HR{$cityCode}001",
            'password' => Hash::make('password'),
            'company_id' => $company->id,
            'department_id' => $hr->id,
            'position' => 'HR Staff'
        ]);
        $user3->assignRole('user');
    }
}