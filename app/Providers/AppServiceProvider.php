<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $appUrl = rtrim((string) config('app.url'), '/');
        $rootUrl = static::urlSemSufixoPublic($appUrl);

        if ($rootUrl !== '' && $rootUrl !== $appUrl) {
            URL::forceRootUrl($rootUrl);
        }

        if (str_starts_with($rootUrl !== '' ? $rootUrl : $appUrl, 'https://')) {
            URL::forceScheme('https');
        }
    }

    /**
     * Hostinger: APP_URL costuma ser …/server/public. As rotas públicas
     * devem ficar em /server/login, não em /server/public/login.
     */
    public static function urlSemSufixoPublic(?string $appUrl): string
    {
        $appUrl = rtrim((string) $appUrl, '/');

        if (str_ends_with($appUrl, '/public')) {
            return substr($appUrl, 0, -strlen('/public'));
        }

        return $appUrl;
    }
}
