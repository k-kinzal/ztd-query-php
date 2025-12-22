<?php

declare(strict_types=1);

namespace Tests\Unit\Fixture;

final class UnitFixtureTest
{
    private int $state = 0;

    private const UNIT_FLAG = 'unit';

    private function helper(): void
    {
    }

    public function testUnit(): void
    {
    }
}

namespace Tests\Integration\Fixture;

final class IntegrationFixtureTest
{
    public string $name = '';

    protected const INTEGRATION_FLAG = 'integration';

    private function prepare(): void
    {
    }

    public function testIntegration(): void
    {
    }
}

namespace App\Tests;

final class OutsideScopeFixtureTest
{
    private int $allowedState = 0;

    private const ALLOWED_CONST = 1;

    private function allowedPrivateMethod(): void
    {
    }
}
