<?php

declare(strict_types=1);

namespace Obelaw\SystemOne\Clef\Drivers;

use Illuminate\Support\Facades\Http;
use Obelaw\SystemOne\Clef\Exceptions\DriverException;
use Obelaw\SystemOne\Contracts\DecisionResponseInterface;
use Obelaw\SystemOne\Contracts\DriverInterface;
use Obelaw\SystemOne\DecisionRequest;
use Obelaw\SystemOne\DecisionResponse;
use Throwable;

class CloudflareDriver implements DriverInterface
{
    public const DEFAULT_MODEL = '@cf/cloudflare/clef';

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        protected array $config = []
    ) {
    }

    /**
     * Execute the decision request with Cloudflare Workers AI.
     *
     * @throws DriverException
     */
    public function decide(DecisionRequest $request): DecisionResponseInterface
    {
        $accountId = (string) ($this->config['account_id'] ?? '');
        $apiToken = (string) ($this->config['api_token'] ?? '');
        $baseUrl = rtrim((string) ($this->config['base_url'] ?? 'https://api.cloudflare.com/client/v4'), '/');
        $timeout = (int) ($this->config['timeout'] ?? 30);

        if (empty($accountId) || empty($apiToken)) {
            throw new DriverException(
                '[Cloudflare Driver] Missing account_id or api_token in configuration.',
                'cloudflare',
                0
            );
        }

        $endpointModel = $this->resolveEndpointModel($request->getModel());
        $payloadModel = $this->resolvePayloadModel($endpointModel, $request->getModel());

        $url = "{$baseUrl}/accounts/{$accountId}/ai/run/{$endpointModel}";
        $payload = array_merge([
            'model' => $payloadModel,
        ], $request->toPayload());

        try {
            $response = Http::withToken($apiToken)
                ->acceptJson()
                ->asJson()
                ->timeout($timeout)
                ->post($url, $payload);
        } catch (Throwable $e) {
            throw new DriverException(
                sprintf('[Cloudflare Driver] Network/connection error: %s', $e->getMessage()),
                'cloudflare',
                0,
                null,
                $e
            );
        }

        if ($response->failed()) {
            throw DriverException::fromResponse('cloudflare', $response);
        }

        if ($response->json('success') === false) {
            $errors = $response->json('errors', []);
            $message = collect($errors)->pluck('message')->filter()->implode(', ') ?: 'Cloudflare API indicated request was unsuccessful.';

            throw new DriverException(
                sprintf('[Cloudflare Driver] API error: %s', $message),
                'cloudflare',
                $response->status(),
                $response->json()
            );
        }

        $answers = $response->json('result.answers') ?? $response->json('answers', []);

        return new DecisionResponse(
            is_array($answers) ? $answers : [],
            $response->json() ?? []
        );
    }

    /**
     * Alias for decide().
     *
     * @throws DriverException
     */
    public function run(DecisionRequest $request): DecisionResponseInterface
    {
        return $this->decide($request);
    }

    /**
     * Resolve the Cloudflare Workers AI model identifier for the API endpoint path.
     */
    public function resolveEndpointModel(?string $model): string
    {
        $rawModel = $model ?? $this->config['model'] ?? self::DEFAULT_MODEL;

        return match ($rawModel) {
            'clef' => '@cf/cloudflare/clef',
            'clef-flash' => '@cf/cloudflare/clef-flash',
            '@cf/clef/decision-maker' => self::DEFAULT_MODEL,
            default => $rawModel,
        };
    }

    /**
     * Resolve the model identifier expected in the JSON payload body.
     */
    public function resolvePayloadModel(string $endpointModel, ?string $requestModel = null): string
    {
        if ($requestModel === 'clef' || $requestModel === 'clef-flash') {
            return $requestModel;
        }

        if ($endpointModel === '@cf/cloudflare/clef') {
            return 'clef';
        }

        if ($endpointModel === '@cf/cloudflare/clef-flash') {
            return 'clef-flash';
        }

        if (!empty($requestModel) && !str_starts_with($requestModel, '@cf/')) {
            return $requestModel;
        }

        $parts = explode('/', $endpointModel);

        return end($parts) ?: 'clef';
    }

    /**
     * Get the driver configuration array.
     *
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->config;
    }
}
