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
                'name' => 'PT. Adijaya Karya Makmur',
                'code' => 'AKM',
                'address' => null,
                'phone' => null,
                'email' => null,
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