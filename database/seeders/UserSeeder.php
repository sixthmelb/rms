<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Department;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $engineering = Department::where('code', 'ENG')->first();
        $scm = Department::where('code', 'SCM')->first();
        
        // Create Admin
        $admin = User::firstOrCreate([
            'email' => 'admin@akm.com'
        ], [
            'name' => 'System Administrator',
            'employee_id' => 'ADM001',
            'password' => Hash::make('password'),
            'department_id' => $engineering->id,
            'position' => 'System Administrator'
        ]);
        $admin->assignRole('admin');

        // Create Sample Users
        $yogi = User::firstOrCreate([
            'email' => 'yogi.ananda@akm.com'
        ], [
            'name' => 'Yogi Ananda',
            'employee_id' => 'EMP001',
            'password' => Hash::make('password'),
            'department_id' => $engineering->id,
            'position' => 'Engineer'
        ]);
        $yogi->assignRole('user');

        // Create Section Head
        $rony = User::firstOrCreate([
            'email' => 'rony.andreas@akm.com'
        ], [
            'name' => 'Rony Andreas',
            'employee_id' => 'SH001',
            'password' => Hash::make('password'),
            'department_id' => $engineering->id,
            'position' => 'Section Head'
        ]);
        $rony->assignRole('section_head');

        // Create SCM Head
        $mandala = User::firstOrCreate([
            'email' => 'mandala@akm.com'
        ], [
            'name' => 'Mandala',
            'employee_id' => 'SCM001',
            'password' => Hash::make('password'),
            'department_id' => $scm->id,
            'position' => 'SCM Section Head'
        ]);
        $mandala->assignRole('scm_head');

        // Create PJO
        $renandus = User::firstOrCreate([
            'email' => 'renandus@akm.com'
        ], [
            'name' => 'Renandus',
            'employee_id' => 'PJO001',
            'password' => Hash::make('password'),
            'department_id' => $engineering->id,
            'position' => 'Project Officer'
        ]);
        $renandus->assignRole('pjo');
    }
}