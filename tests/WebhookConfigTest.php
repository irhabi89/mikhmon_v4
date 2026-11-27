<?php
declare(strict_types=1);
namespace Mikhmon\Tests;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../include/bootstrap.php';

final class WebhookConfigTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('MIKHMON_PUBLIC_URL');
    }

    public function testUrlComesFromEnvironmentAndIncludesSession(): void
    {
        putenv('MIKHMON_PUBLIC_URL=https://mikhmon.example.com/');
        $this->assertSame('https://mikhmon.example.com', \mikhmon_webhook_public_url());
        $this->assertSame(
            'https://mikhmon.example.com/process/sales_webhook.php?session=router%201',
            \mikhmon_webhook_url('router 1')
        );
    }

    public function testDotenvStyleEnvFallbackIsRead(): void
    {
        putenv('MIKHMON_PUBLIC_URL');
        $_ENV['MIKHMON_PUBLIC_URL'] = 'https://mikhmon.ghaib.net';
        $_SERVER['MIKHMON_PUBLIC_URL'] = 'https://mikhmon.ghaib.net';
        try {
            $this->assertSame('https://mikhmon.ghaib.net', \mikhmon_webhook_public_url());
        } finally {
            unset($_ENV['MIKHMON_PUBLIC_URL'], $_SERVER['MIKHMON_PUBLIC_URL']);
        }
    }

    public function testInvalidOrMissingPublicUrlProducesNoWebhookUrl(): void
    {
        putenv('MIKHMON_PUBLIC_URL=not-a-url');
        $this->assertSame('', \mikhmon_webhook_public_url());
        $this->assertSame('', \mikhmon_webhook_url('router1'));
    }
}
