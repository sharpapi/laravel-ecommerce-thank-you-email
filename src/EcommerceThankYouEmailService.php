<?php

declare(strict_types=1);

namespace SharpAPI\EcommerceThankYouEmail;

use GuzzleHttp\Exception\GuzzleException;
use InvalidArgumentException;
use SharpAPI\Core\Client\SharpApiClient;

/**
 * @api
 */
class EcommerceThankYouEmailService extends SharpApiClient
{
    /**
     * Initializes a new instance of the class.
     *
     * @throws InvalidArgumentException if the API key is empty.
     */
    public function __construct()
    {
        parent::__construct(config('sharpapi-ecommerce-thank-you-email.api_key'));
        $this->setApiBaseUrl(
            config(
                'sharpapi-ecommerce-thank-you-email.base_url',
                'https://sharpapi.com/api/v1'
            )
        );
        $this->setApiJobStatusPollingInterval(
            (int) config(
                'sharpapi-ecommerce-thank-you-email.api_job_status_polling_interval',
                5)
        );
        $this->setApiJobStatusPollingWait(
            (int) config(
                'sharpapi-ecommerce-thank-you-email.api_job_status_polling_wait',
                180)
        );
        $this->setUserAgent('SharpAPILaravelEcommerceThankYouEmail/1.0.0');
    }

    /**
     * Generates a personalized thank-you email to the customer after the purchase.
     * The response content does not contain the title, greeting or sender info at the end,
     * so you can personalize the rest of the email easily.
     *
     * @throws GuzzleException
     *
     * @api
     */
    public function generateThankYouEmail(
        string $productName,
        ?string $language = null,
        ?int $maxLength = null,
        ?string $voiceTone = null,
        ?string $context = null
    ): string {
        $response = $this->makeRequest(
            'POST',
            '/ecommerce/thank_you_email',
            [
                'content' => $productName,
                'language' => $language,
                'max_length' => $maxLength,
                'voice_tone' => $voiceTone,
                'context' => $context,
            ]);

        return $this->parseStatusUrl($response);
    }
}