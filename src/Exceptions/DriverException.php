<?php

declare(strict_types=1);

namespace Obelaw\SystemOne\Clef\Exceptions;

use Illuminate\Http\Client\Response;
use Throwable;

class DriverException extends ClefException
{
    /**
     * @param string $message
     * @param string $driver
     * @param int $status
     * @param array<string, mixed>|null $responseBody
     * @param Throwable|null $previous
     */
    public function __construct(
        string $message,
        protected string $driver = 'cloudflare',
        protected int $status = 0,
        protected ?array $responseBody = null,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $status, $previous);
    }

    /**
     * Create an exception instance from an HTTP Response.
     */
    public static function fromResponse(string $driver, Response $response): static
    {
        $status = $response->status();
        $json = $response->json();
        $errors = is_array($json) && isset($json['errors']) ? $json['errors'] : [];

        $message = match ($status) {
            401 => 'Unauthorized: Invalid Cloudflare API token or insufficient permissions.',
            403 => 'Forbidden: Access to Cloudflare Workers AI model is restricted.',
            404 => 'Model or account endpoint not found on Cloudflare Workers AI.',
            422 => 'Unprocessable entity: Cloudflare model rejected decision payload schema.',
            429 => 'Rate limit exceeded on Cloudflare Workers AI.',
            500, 502, 503, 504 => sprintf('Cloudflare server error (HTTP %d).', $status),
            default => sprintf('Cloudflare request failed with HTTP status %d.', $status),
        };

        if (!empty($errors) && is_array($errors)) {
            $errorMsgs = collect($errors)->pluck('message')->filter()->implode(', ');
            if ($errorMsgs !== '') {
                $message .= " Details: {$errorMsgs}";
            }
        }

        return new static(
            sprintf('[%s Driver] %s', ucfirst($driver), $message),
            $driver,
            $status,
            is_array($json) ? $json : null
        );
    }

    public function getDriver(): string
    {
        return $this->driver;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getResponseBody(): ?array
    {
        return $this->responseBody;
    }
}
