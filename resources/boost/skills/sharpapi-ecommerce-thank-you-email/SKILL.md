---
name: sharpapi-ecommerce-thank-you-email
description: Generate a post-purchase thank-you email body via SharpAPI (sharpapi/laravel-ecommerce-thank-you-email). Use when writing order confirmation or thank-you emails with AI, or when code touches EcommerceThankYouEmailService, generateThankYouEmail(), fetchResults() or config/sharpapi-ecommerce-thank-you-email.php.
---

# SharpAPI E-commerce Thank-You Email (`sharpapi/laravel-ecommerce-thank-you-email`)

Writes a personalised post-purchase thank-you email body for a product. The package is a thin Laravel wrapper around `sharpapi/php-core`: one service class (`SharpAPI\EcommerceThankYouEmail\EcommerceThankYouEmailService`), one config file, no facade and no container binding. Requires `sharpapi/php-core` ≥ 1.4.1 (pulled in by v1.1.0 of this package).

## When to use this skill

- Generating a thank-you or post-purchase email for a customer with AI.
- Writing or reviewing code that calls `EcommerceThankYouEmailService::generateThankYouEmail()` or `fetchResults()`, or reads `config('sharpapi-ecommerce-thank-you-email.*')`.
- Moving a SharpAPI call out of a web request into a queued job, or writing tests around it.

## Install / wiring checklist

1. `composer require sharpapi/laravel-ecommerce-thank-you-email`. `SharpAPI\EcommerceThankYouEmail\EcommerceThankYouEmailProvider` is auto-discovered; it only merges and publishes config.
2. Set `SHARP_API_KEY` in `.env`. The service constructor throws `InvalidArgumentException` when the key is empty, so resolving the service without a key fails at once (in tests too).
3. Optional: `php artisan vendor:publish --tag=sharpapi-ecommerce-thank-you-email` copies `config/sharpapi-ecommerce-thank-you-email.php`. Only publish it if you need per-package overrides; the env keys below already work without it.
4. Get the service by type-hinting `EcommerceThankYouEmailService` (constructor or `handle()` injection) or `app(EcommerceThankYouEmailService::class)`. `new EcommerceThankYouEmailService()` works too (no constructor args) but tests can't swap it with `$this->mock()`.

## API & config reference

### Config: `config/sharpapi-ecommerce-thank-you-email.php`

| Key | Env | Default | Effect |
|---|---|---|---|
| `api_key` | `SHARP_API_KEY` | none | Required. |
| `base_url` | `SHARP_API_BASE_URL` | `https://sharpapi.com/api/v1` | API base URL. |
| `api_job_status_polling_wait` | `SHARP_API_JOB_STATUS_POLLING_WAIT` | `180` | Seconds `fetchResults()` keeps polling before it throws `ApiException`. |
| `api_job_status_polling_interval` | `SHARP_API_JOB_STATUS_POLLING_INTERVAL` | `10` | Seconds between status checks when the API sends no `Retry-After` header, or always when the next key is `true`. |
| `api_job_status_use_polling_interval` | `SHARP_API_JOB_STATUS_USE_POLLING_INTERVAL` | `false` | `true` ignores `Retry-After` and polls every `api_job_status_polling_interval` seconds. Applied since v1.1.0; older versions ignored it. |

The other `sharpapi/laravel-*` wrappers read the same `SHARP_API_*` env keys, so one `.env` block configures all of them. The service also inherits the php-core setters (`setApiJobStatusPollingWait()`, `setApiJobStatusPollingInterval()`, `setUseCustomInterval()`) for per-instance overrides.

### Submit: `generateThankYouEmail()`

```php
public function generateThankYouEmail(
    string $productName,
    ?string $language = null,
    ?int $maxLength = null,
    ?string $voiceTone = null,
    ?string $context = null
): string
```

| Parameter | Type | Default | Meaning |
|---|---|---|---|
| `$productName` | `string` | (required) | The purchased product (name, optionally with details). Sent as `content`. |
| `$language` | `?string` | `null` | Output language as a full English name ("English", "German"), not an ISO code. `null` leaves it to the API. |
| `$maxLength` | `?int` | `null` | Length limit for the email, sent as `max_length`. The package does not state the unit; the README example uses 250. |
| `$voiceTone` | `?string` | `null` | Free-text tone for the generated copy, e.g. "Professional", "Friendly", "Tech-savvy". Sent as `voice_tone`. |
| `$context` | `?string` | `null` | Extra free-text instructions or background that steer the output. Sent as `context`. |

Sends `POST /ecommerce/thank_you_email` and returns the job's **status URL** (a string), not the result.

### Collect: `fetchResults()`

```php
public function fetchResults(string $statusUrl): \SharpAPI\Core\DTO\SharpApiJob
```

Blocks and polls until the job is `success` or `failed`, honouring `Retry-After` and the polling wait. `SharpApiJob` has public `id`, `type`, `status` (a plain string) and `?stdClass $result`, plus `getResultJson()`, `getResultArray()` (shallow) and `getResultObject()`.

### Result

Example (shortened from the README). `fetchResults()` returns only the `result` part; the README shows it inside the full `data.attributes` envelope.

```json
{
  "email": "Dear Customer,\n\nThank you for your recent purchase of the Razer Blade 16 Gaming Laptop ... We look forward to serving you again.\n\nBest regards,\n[Your Company Name]"
}
```

- The body is plain text with `\n` line breaks in `email`. Escape it and convert line breaks (`nl2br(e($text))`) before putting it in an HTML mail.
- The service docblock says the text leaves out the title, greeting and sign-off, but the README example contains "Dear Customer," and a "[Your Company Name]" placeholder. Check the output before you wrap it in your own greeting or signature.

### Exceptions

- `InvalidArgumentException`: empty `SHARP_API_KEY`, thrown by the service constructor.
- `SharpAPI\Core\Exceptions\ApiException`: polling ran past `api_job_status_polling_wait`, or HTTP 429 retries ran out.
- `GuzzleHttp\Exception\ClientException` (4xx such as 401 bad key or 422 validation) and other `GuzzleHttp\Exception\GuzzleException`s (5xx, network): from either call.
- A job that finishes with status `failed` does **not** throw. See Gotchas.

## Recipes

### Queued job (the default way to call it)

```php
<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use SharpAPI\Core\Enums\SharpApiJobStatusEnum;
use SharpAPI\EcommerceThankYouEmail\EcommerceThankYouEmailService;

class GenerateThankYouEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Must exceed SHARP_API_JOB_STATUS_POLLING_WAIT (180 s by default) plus request time.
    public int $timeout = 300;

    // Every retry submits a new SharpAPI job and spends quota again.
    public int $tries = 1;

    public function __construct(public string $productName) {}

    public function handle(EcommerceThankYouEmailService $service): void
    {
        $statusUrl = $service->generateThankYouEmail(
            $this->productName,
            language: 'English',
            maxLength: 250,
            voiceTone: 'Friendly',
            context: 'Mention our loyalty programme',
        );

        $job = $service->fetchResults($statusUrl);

        if ($job->status !== SharpApiJobStatusEnum::SUCCESS->value) {
            Log::warning('SharpAPI generateThankYouEmail failed', ['job_id' => $job->id, 'status' => $job->status]);

            return;
        }

        $result = json_decode($job->getResultJson(), true);

        // ... persist $result
    }
}
```

Dispatch it with `GenerateThankYouEmail::dispatch(...)`. The worker's `--timeout` (or the Horizon supervisor `timeout`) and the queue connection's `retry_after` must both be at least the job's `$timeout`. Otherwise the worker kills the job mid-poll, or a second worker picks it up and submits it again.

### Reading the result

```php
$body = $job->getResultObject()->email ?? null;
```

## Gotchas

1. `fetchResults()` already blocks and polls, up to `api_job_status_polling_wait` seconds, and honours `Retry-After`. Never write your own `while ($job->status === 'pending')` loop or call `fetchResults()` repeatedly.
2. A failed job does not throw. Compare `$job->status` with `SharpApiJobStatusEnum::SUCCESS->value` (`SharpAPI\Core\Enums\SharpApiJobStatusEnum`, values `new`, `pending`, `failed`, `success`) before reading the result. A failed job's `$result` carries no usable payload, so `->field` access on it gives undefined-property warnings and `null`s.
3. Never call `fetchResults()` inside an HTTP request, Nova action or Livewire action that runs synchronously: it can block for minutes. Use a queued job whose `$timeout` exceeds the polling wait. Keep `$tries` low (1-2), because each retry re-submits and burns quota. Worker/Horizon `timeout` and `retry_after` must be ≥ the job `$timeout`.
4. For reliable arrays use `json_decode($job->getResultJson(), true)`. `getResultArray()` only converts the top level, so nested values stay `stdClass`, and a list result comes back as an object with numeric keys.
5. Language and voice tone are free-text full names ("English", "Spanish", "Professional"), not ISO or locale codes. Passing `en` or an enum `->value` gives unpredictable output; pass a human label.

## Testing

`Http::fake()` does **not** intercept this package: php-core sends requests through its own Guzzle client. Mock the service instead. That only works when your code resolves it from the container (DI or `app()`), not with `new`.

```php
use Mockery\MockInterface;
use SharpAPI\Core\DTO\SharpApiJob;
use SharpAPI\EcommerceThankYouEmail\EcommerceThankYouEmailService;

$this->mock(EcommerceThankYouEmailService::class, function (MockInterface $mock) {
    $mock->shouldReceive('generateThankYouEmail')->once()->andReturn('https://sharpapi.com/api/v1/job/status/test-job');
    $mock->shouldReceive('fetchResults')->once()->andReturn(new SharpApiJob(
        id: 'test-job',
        type: 'ecommerce_thank_you_email',
        status: 'success',
        result: (object) json_decode(json_encode(['email' => "Dear Customer,\n\nThank you for your order."])),
    ));
});
```

- Also cover the failure branch: return a `SharpApiJob` with `status: 'failed'` and `result: null`, and assert nothing gets persisted.
- The real constructor needs a non-empty key. Set `SHARP_API_KEY` (any value) in `phpunit.xml` if a test resolves the real service.
- Queue tests: `Queue::fake()` plus `Queue::assertPushed(GenerateThankYouEmail::class)`, then test `handle()` separately with the mocked service.
