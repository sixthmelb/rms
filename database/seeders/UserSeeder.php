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
        // Get AKM company and IT department
        $akmCompany = Company::where('code', 'AKM')->first();
        $itDepartment = Department::where('code', 'IT')->where('company_id', $akmCompany->id)->first();

        // System Administrator
        $admin = User::firstOrCreate([
            'email' => 'admin@akm.com'
        ], [
            'name' => 'System Administrator',
            'employee_id' => 'ADM001',
            'password' => Hash::make('@d1jaya?'),
            'company_id' => $akmCompany->id,
            'department_id' => $itDepartment->id,
            'position' => 'System Administrator'
        ]);
        $admin->assignRole('admin');

        // Approvers
        $rony = User::firstOrCreate([
            'email' => 'rony.andreas@akm.com'
        ], [
            'name' => 'Rony Andreas S.',
            'employee_id' => 'AKM001',
            'password' => Hash::make('@d1jaya?'),
            'company_id' => $akmCompany->id,
            'department_id' => $itDepartment->id,
            'position' => 'Section Head HCDGA/IT'
        ]);
        $rony->assignRole('section_head');

        $mandala = User::firstOrCreate([
            'email' => 'mandala@akm.com'
        ], [
            'name' => 'Mandala',
            'employee_id' => 'SCM001',
            'password' => Hash::make('@d1jaya?'),
            'company_id' => $akmCompany->id,
            'department_id' => $itDepartment->id,
            'position' => 'Section Head SCM'
        ]);
        $mandala->assignRole('scm_head');

        $renandus = User::firstOrCreate([
            'email' => 'renandus@akm.com'
        ], [
            'name' => 'Renandus',
            'employee_id' => 'PJO001',
            'password' => Hash::make('@d1jaya?'),
            'company_id' => $akmCompany->id,
            'department_id' => $itDepartment->id,
            'position' => 'PJO AKM'
        ]);
        $renandus->assignRole('pjo');

        // Regular Users
        $maxWilliam = User::firstOrCreate([
            'email' => 'max.william@akm.com'
        ], [
            'name' => 'Max William',
            'employee_id' => 'IT001',
            'password' => Hash::make('@d1jaya?'),
            'company_id' => $akmCompany->id,
            'department_id' => $itDepartment->id,
            'position' => 'IT Staff'
        ]);
        $maxWilliam->assignRole('user');

        $edowardo = User::firstOrCreate([
            'email' => 'edowardo.romon@akm.com'
        ], [
            'name' => 'Edowardo Romon',
            'employee_id' => 'IT002',
            'password' => Hash::make('@d1jaya?'),
            'company_id' => $akmCompany->id,
            'department_id' => $itDepartment->id,
            'position' => 'IT Crew'
        ]);
        $edowardo->assignRole('user');

        $adeYogi = User::firstOrCreate([
            'email' => 'ade.yogi@akm.com'
        ], [
            'name' => 'Ade Yogi Finanda',
            'employee_id' => 'IT003',
            'password' => Hash::make('@d1jaya?'),
            'company_id' => $akmCompany->id,
            'department_id' => $itDepartment->id,
            'position' => 'IT Crew'
        ]);
        $adeYogi->assignRole('user');

        $adePutera = User::firstOrCreate([
            'email' => 'ade.putera@akm.com'
        ], [
            'name' => 'Ade Putera Ramadhan',
            'employee_id' => 'IT004',
            'password' => Hash::make('@d1jaya?'),
            'company_id' => $akmCompany->id,
            'department_id' => $itDepartment->id,
            'position' => 'IT Crew'
        ]);
        $adePutera->assignRole('user');

        $yogiAnanda = User::firstOrCreate([
            'email' => 'yogi.ananda@akm.com'
        ], [
            'name' => 'Yogi Ananda',
            'employee_id' => 'IT005',
            'password' => Hash::make('@d1jaya?'),
            'company_id' => $akmCompany->id,
            'department_id' => $itDepartment->id,
            'position' => 'IT Staff'
        ]);
        $yogiAnanda->assignRole('user');
    }
}