<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Filament\Support\Facades\FilamentView;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Create QR codes directory if not exists
        if (!file_exists(storage_path('app/public/qr_codes'))) {
            mkdir(storage_path('app/public/qr_codes'), 0755, true);
        }
    }
}