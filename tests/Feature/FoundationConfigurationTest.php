<?php

namespace Tests\Feature;

use Tests\TestCase;

class FoundationConfigurationTest extends TestCase
{
    public function test_application_uses_the_expected_regional_configuration(): void
    {
        $this->assertSame('es', config('app.locale'));
        $this->assertSame('es', config('app.fallback_locale'));
        $this->assertSame('America/La_Paz', config('app.timezone'));
    }
}
