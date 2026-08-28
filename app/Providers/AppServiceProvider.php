<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Model::preventLazyLoading(! app()->isProduction() || app()->environment('testing'));
        Model::preventAccessingMissingAttributes(! app()->isProduction());
        DB::prohibitDestructiveCommands(app()->isProduction());
    }
}
