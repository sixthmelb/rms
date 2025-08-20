<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Company;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        $companies = [
            [
                'name' => 'PT. Adijaya Karya Makmur - Jakarta',
                'code' => 'AKM-JKT',
                'address' => 'Jakarta Office, Indonesia',
                'phone' => '+62-21-1234567',
                'email' => 'info@akm-jakarta.com',
                'is_active' => true,
            ],
            [
                'name' => 'PT. Adijaya Karya Makmur - Surabaya', 
                'code' => 'AKM-SBY',
                'address' => 'Surabaya Office, Indonesia',
                'phone' => '+62-31-7654321',
                'email' => 'info@akm-surabaya.com',
                'is_active' => true,
            ],
            [
                'name' => 'PT. Adijaya Karya Makmur - Bandung',
                'code' => 'AKM-BDG', 
                'address' => 'Bandung Office, Indonesia',
                'phone' => '+62-22-9876543',
                'email' => 'info@akm-bandung.com',
                'is_active' => true,
            ],
            [
                'name' => 'Central SCM Division',
                'code' => 'SCM-CTR',
                'address' => 'Central SCM Office',
                'phone' => '+62-21-1111111',
                'email' => 'scm@akm.com',
                'is_active' => true,
            ],
        ];

        foreach ($companies as $company) {
            Company::firstOrCreate(
                ['code' => $company['code']],
                $company
            );
        }
    }
}