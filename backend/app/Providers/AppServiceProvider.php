<?php

namespace App\Providers;

use App\Services\ContentGenerationService;
use App\Services\MockContentGenerationService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ContentGenerationService::class, MockContentGenerationService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
