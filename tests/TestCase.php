<?php

declare(strict_types=1);

namespace SharpAPI\EcommerceThankYouEmail\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use SharpAPI\EcommerceThankYouEmail\EcommerceThankYouEmailProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [EcommerceThankYouEmailProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('sharpapi-ecommerce-thank-you-email.api_key', 'test-key');
    }
}
