<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Obelaw\SystemOne\Clef\Drivers\CloudflareDriver;
use Obelaw\SystemOne\Clef\Tests\TestCase;
use Obelaw\SystemOne\Contracts\DecisionResponseInterface;
use Obelaw\SystemOne\DecisionRequest;
use Obelaw\SystemOne\Facades\SystemOne;
use Obelaw\SystemOne\Primitives\Choice;
use Obelaw\SystemOne\Primitives\Noul;
use Obelaw\SystemOne\Primitives\Score;

uses(TestCase::class);

describe('CloudflareDriver HTTP Mocking & Workers AI Integration', function () {
    it('sends properly structured request with Bearer authorization and parses Clef answers', function () {
        $mockAccountId = 'test-cf-account-12345';
        $mockApiToken = 'test-cf-token-secret';
        $expectedUrl = "https://api.cloudflare.com/client/v4/accounts/{$mockAccountId}/ai/run/@cf/cloudflare/clef";

        Http::fake([
            $expectedUrl => Http::response([
                'result' => [
                    'answers' => [
                        'urgent' => [
                            'value' => 0.942,
                            'confidence' => 0.942,
                        ],
                        'department' => [
                            'value' => 'security',
                            'confidence' => 0.89,
                            'probabilities' => [
                                'security' => 0.89,
                                'billing' => 0.08,
                                'sales' => 0.03,
                            ],
                        ],
                        'severity' => [
                            'value' => 4.8,
                            'confidence' => 0.91,
                        ],
                    ],
                ],
                'success' => true,
                'errors' => [],
                'messages' => [],
            ], 200),
        ]);

        $driver = new CloudflareDriver([
            'account_id' => $mockAccountId,
            'api_token' => $mockApiToken,
            'model' => '@cf/cloudflare/clef',
            'base_url' => 'https://api.cloudflare.com/client/v4',
        ]);

        $request = (new DecisionRequest())
            ->state('Data breach attempt detected on production cluster.')
            ->ask('urgent', Noul::make('Is this incident urgent?'))
            ->ask('department', Choice::make('Which team handles this?', [
                'security' => 'Cybersecurity & incident response',
                'billing' => 'Invoicing and revenue',
                'sales' => 'Lead qualification',
            ]))
            ->ask('severity', Score::rubric('Rate severity level', [
                'Low', 'Medium', 'High', 'Critical',
            ]));

        $response = $driver->decide($request);

        expect($response)->toBeInstanceOf(DecisionResponseInterface::class)
            ->and($response->value('urgent'))->toBe(0.942)
            ->and($response->confidence('urgent'))->toBe(0.942)
            ->and($response->isPositive('urgent'))->toBeTrue()
            ->and($response->value('department'))->toBe('security')
            ->and($response->probabilities('department'))->toBe([
                'security' => 0.89,
                'billing' => 0.08,
                'sales' => 0.03,
            ])
            ->and($response->value('severity'))->toBe(4.8);

        Http::assertSent(function ($request) use ($expectedUrl, $mockApiToken) {
            $hasAuth = $request->hasHeader('Authorization', "Bearer {$mockApiToken}");
            $isCorrectUrl = $request->url() === $expectedUrl;
            $body = $request->data();

            return $hasAuth
                && $isCorrectUrl
                && $body['model'] === 'clef'
                && $body['state'] === 'Data breach attempt detected on production cluster.'
                && isset($body['questions']['urgent'])
                && isset($body['questions']['department'])
                && isset($body['questions']['severity']);
        });
    });

    it('allows overriding model dynamically to clef-flash on CloudflareDriver', function () {
        $mockAccountId = 'test-cf-account-12345';
        $expectedUrl = "https://api.cloudflare.com/client/v4/accounts/{$mockAccountId}/ai/run/@cf/cloudflare/clef-flash";

        Http::fake([
            $expectedUrl => Http::response([
                'result' => [
                    'answers' => [
                        'fast_check' => [
                            'value' => 0.99,
                            'confidence' => 0.99,
                        ],
                    ],
                ],
                'success' => true,
            ], 200),
        ]);

        $driver = new CloudflareDriver([
            'account_id' => $mockAccountId,
            'api_token' => 'test-cf-token-secret',
            'model' => '@cf/cloudflare/clef',
        ]);

        $request = (new DecisionRequest())
            ->model('clef-flash')
            ->state('Ping latency spike')
            ->ask('fast_check', Noul::make('Is quick action needed?'));

        $response = $driver->decide($request);

        expect($response->value('fast_check'))->toBe(0.99);

        Http::assertSent(function ($request) use ($expectedUrl) {
            return $request->url() === $expectedUrl
                && $request->data()['model'] === 'clef-flash';
        });
    });

    it('executes seamlessly via SystemOne manager and facade with cloudflare driver', function () {
        $expectedUrl = 'https://api.cloudflare.com/client/v4/accounts/test-cf-account-12345/ai/run/@cf/cloudflare/clef';

        Http::fake([
            $expectedUrl => Http::response([
                'result' => [
                    'answers' => [
                        'qualified' => [
                            'value' => 'enterprise',
                            'confidence' => 0.96,
                        ],
                    ],
                ],
                'success' => true,
            ], 200),
        ]);

        $response = SystemOne::state('User has 500 team members and requested SSO')
            ->driver('cloudflare')
            ->ask('qualified', Choice::make('Plan tier', ['starter', 'pro', 'enterprise']))
            ->run();

        expect($response->value('qualified'))->toBe('enterprise')
            ->and($response->confidence('qualified'))->toBe(0.96);
    });
});
