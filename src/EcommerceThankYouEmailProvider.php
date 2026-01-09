<?php

declare(strict_types=1);

namespace SharpAPI\EcommerceThankYouEmail;

use Illuminate\Support\ServiceProvider;

/**
 * @api
 */
class EcommerceThankYouEmailProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/sharpapi-ecommerce-thank-you-email.php' => config_path('sharpapi-ecommerce-thank-you-email.php'),
            ], 'sharpapi-ecommerce-thank-you-email');
        }
    }

    /**
     * Register the application services.
     */
    public function register(): void
    {
        // Merge the package configuration with the app configuration.
        $this->mergeConfigFrom(
            __DIR__.'/../config/sharpapi-ecommerce-thank-you-email.php', 'sharpapi-ecommerce-thank-you-email'
        );
    }
}