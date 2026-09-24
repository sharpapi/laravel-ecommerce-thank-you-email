<?php

declare(strict_types=1);

use SharpAPI\EcommerceThankYouEmail\EcommerceThankYouEmailService;

it('applies the use-polling-interval flag from config', function (bool $flag): void {
    config()->set('sharpapi-ecommerce-thank-you-email.api_job_status_use_polling_interval', $flag);

    expect((new EcommerceThankYouEmailService)->isUseCustomInterval())->toBe($flag);
})->with([true, false]);

it('reads the polling settings from config', function (): void {
    config()->set('sharpapi-ecommerce-thank-you-email.api_job_status_polling_interval', 7);
    config()->set('sharpapi-ecommerce-thank-you-email.api_job_status_polling_wait', 42);

    $service = new EcommerceThankYouEmailService;

    expect($service->getApiJobStatusPollingInterval())->toBe(7)
        ->and($service->getApiJobStatusPollingWait())->toBe(42);
});

it('throws a clear exception when the API key is missing', function (): void {
    config()->set('sharpapi-ecommerce-thank-you-email.api_key', null);

    new EcommerceThankYouEmailService;
})->throws(InvalidArgumentException::class);
