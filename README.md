# Obelaw SystemOne Clef Driver

[![Pest Tests](https://img.shields.io/badge/Pest-Passing-brightgreen)](https://pestphp.com)
[![PHP](https://img.shields.io/badge/PHP-8.2+-blue)](https://php.net)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

**SystemOne Clef** is the production-ready Cloudflare Workers AI driver adapter for the Obelaw `SystemOne` Decision Engine.

## Installation

```bash
composer require obelaw/systemone-clef
```

## Configuration

Publish configuration:

```bash
php artisan vendor:publish --tag="systemone-clef-config"
```

Add your Cloudflare Workers AI credentials to `.env`:

```env
CLOUDFLARE_ACCOUNT_ID=your-account-id
CLOUDFLARE_API_TOKEN=your-workers-ai-api-token
CLOUDFLARE_AI_MODEL=@cf/cloudflare/clef
```

## Endpoint Architecture

Sends requests to Cloudflare Workers AI:
`POST https://api.cloudflare.com/client/v4/accounts/{account_id}/ai/run/{model}`

With headers:
- `Authorization: Bearer {api_token}`
- `Content-Type: application/json`

And automatically parses calibrated probability answers from `result.answers`.
