<?php

declare(strict_types=1);

namespace MattAllan\PassportSocialite;

use Illuminate\Support\ServiceProvider;
use Laravel\Socialite\Facades\Socialite;

class PassportSocialiteServiceProvider extends ServiceProvider
{
    public function boot()
    {
        Socialite::extend('passport', function () {
            return Socialite::buildProvider(
                PassportProvider::class,
                $this->app['config']['services.passport']
            );
        });
    }
}
