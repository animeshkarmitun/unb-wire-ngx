<?php

namespace App\Providers;

use App\Support\RateLimitHelper;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Model::preventLazyLoading(! app()->isProduction() || app()->environment('testing'));
        Model::preventAccessingMissingAttributes(! app()->isProduction());
        DB::prohibitDestructiveCommands(app()->isProduction());

        $this->registerRateLimiters();
    }

    private function registerRateLimiters(): void
    {
        if (RateLimitHelper::disabled()) {
            return;
        }

        $limits = [
            'portal-login' => 'portal_login',
            'portal-forgot-pw' => 'portal_forgot_pw',
            'portal-reset-pw' => 'portal_reset_pw',
            'portal-feed' => 'portal_feed',
            'portal-story' => 'portal_story',
            'portal-search-token' => 'portal_search_token',
            'portal-session' => 'portal_session',
            'client-feed' => 'client_feed',
            'media-download' => 'media_download',
            'staff-upload' => 'staff_upload',
            'ai-assist' => 'ai_assist',
            'email-verify' => 'email_verify',
        ];

        foreach ($limits as $name => $configKey) {
            RateLimiter::for($name, fn (Request $req) => Limit::perMinutes(RateLimitHelper::decay($configKey), RateLimitHelper::attempts($configKey))
                ->by($req->ip())
            );
        }

        RateLimiter::for('staff-login', function (Request $req) {
            return Limit::perMinutes(
                RateLimitHelper::decay('staff_login'),
                RateLimitHelper::attempts('staff_login')
            )->by($req->ip());
        });
    }
}
