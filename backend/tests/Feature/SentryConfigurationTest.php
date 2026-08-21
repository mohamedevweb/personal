<?php

namespace Tests\Feature;

use Tests\TestCase;

class SentryConfigurationTest extends TestCase
{
    public function test_sentry_is_safe_and_disabled_without_a_dsn(): void
    {
        $this->assertSame('', config('sentry.dsn'));
        $this->assertFalse(config('sentry.send_default_pii'));
        $this->assertContains('/up', config('sentry.ignore_transactions'));
    }
}
