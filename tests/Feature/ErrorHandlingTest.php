<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Obelaw\SystemOne\Clef\Drivers\CloudflareDriver;
use Obelaw\SystemOne\Clef\Exceptions\DriverException;
use Obelaw\SystemOne\Clef\Tests\TestCase;
use Obelaw\SystemOne\DecisionRequest;
use Obelaw\SystemOne\Primitives\Noul;

uses(TestCase::class);

describe('CloudflareDriver Error Handling & Exceptions', function () {
    it('throws informative DriverException when Cloudflare responds with HTTP 401 Unauthorized', function () {
        Http::fake([
            'https://api.cloudflare.com/*' => Http::response([
                'success' => false,
                'errors' => [
                    ['code' => 10000, 'message' => 'Authentication error: Invalid API token'],
                ],
            ], 401),
        ]);

        $driver = new CloudflareDriver([
            'account_id' => 'acc_test',
            'api_token' => 'invalid_token',
        ]);

        $request = (new DecisionRequest())
            ->state('Test state')
            ->ask('check', Noul::make('Is ok?'));

        expect(fn() => $driver->decide($request))
            ->toThrow(function (DriverException $e) {
                expect($e->getStatus())->toBe(401)
                    ->and($e->getDriver())->toBe('cloudflare')
                    ->and($e->getMessage())->toContain('Unauthorized')
                    ->and($e->getMessage())->toContain('Authentication error: Invalid API token');
            });
    });

    it('throws informative DriverException when Cloudflare responds with HTTP 500 Server Error', function () {
        Http::fake([
            'https://api.cloudflare.com/*' => Http::response([
                'success' => false,
                'errors' => [
                    ['code' => 5000, 'message' => 'Internal Workers AI error occurred'],
                ],
            ], 500),
        ]);

        $driver = new CloudflareDriver([
            'account_id' => 'acc_test',
            'api_token' => 'token_test',
        ]);

        $request = (new DecisionRequest())
            ->state('Test state')
            ->ask('check', Noul::make('Is ok?'));

        expect(fn() => $driver->decide($request))
            ->toThrow(function (DriverException $e) {
                expect($e->getStatus())->toBe(500)
                    ->and($e->getDriver())->toBe('cloudflare')
                    ->and($e->getMessage())->toContain('Cloudflare server error (HTTP 500)');
            });
    });

    it('throws DriverException when Cloudflare returns HTTP 200 with success false', function () {
        Http::fake([
            'https://api.cloudflare.com/*' => Http::response([
                'result' => null,
                'success' => false,
                'errors' => [
                    ['code' => 1010, 'message' => 'Model capacity currently exceeded'],
                ],
                'messages' => [],
            ], 200),
        ]);

        $driver = new CloudflareDriver([
            'account_id' => 'acc_test',
            'api_token' => 'token_test',
        ]);

        $request = (new DecisionRequest())
            ->state('State content')
            ->ask('check', Noul::make('Is ok?'));

        expect(fn() => $driver->decide($request))
            ->toThrow(function (DriverException $e) {
                expect($e->getMessage())->toContain('Model capacity currently exceeded');
            });
    });

    it('throws DriverException when Cloudflare driver is missing configuration', function () {
        $driver = new CloudflareDriver([
            'account_id' => '',
            'api_token' => '',
        ]);

        $request = (new DecisionRequest())
            ->state('State content')
            ->ask('check', Noul::make('Is ok?'));

        expect(fn() => $driver->decide($request))
            ->toThrow(DriverException::class, '[Cloudflare Driver] Missing account_id or api_token in configuration.');
    });

    it('handles network connection exceptions gracefully', function () {
        Http::fake([
            'https://api.cloudflare.com/*' => function () {
                throw new ConnectionException('cURL error 28: Connection timed out after 30000 milliseconds');
            },
        ]);

        $driver = new CloudflareDriver([
            'account_id' => 'acc_test',
            'api_token' => 'token_test',
        ]);

        $request = (new DecisionRequest())
            ->state('State content')
            ->ask('check', Noul::make('Is ok?'));

        expect(fn() => $driver->decide($request))
            ->toThrow(function (DriverException $e) {
                expect($e->getMessage())->toContain('Network/connection error')
                    ->and($e->getMessage())->toContain('Connection timed out');
            });
    });
});
