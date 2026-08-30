<?php

declare(strict_types=1);

namespace Tests\Unit\Fixture;

use PHPUnit\Framework\TestCase;

final class NonOverrideMethodFixtureTest extends TestCase
{
    public function testSomething(): void
    {
        self::assertTrue(true);
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();
    }

    #[\Override]
    protected function tearDown(): void
    {
        parent::tearDown();
    }

    protected function assertPostConditions(): void
    {
    }

    protected function customHelper(): void
    {
    }

    /**
     * @return \Generator<string, array{string}>
     */
    public static function providerSomeData(): \Generator
    {
        yield 'case1' => ['value1'];
    }
}

namespace Tests\Integration\Fixture;

use PHPUnit\Framework\TestCase;

final class IntegrationNonOverrideFixtureTest extends TestCase
{
    public function testIntegration(): void
    {
        self::assertTrue(true);
    }

    #[\Override]
    protected function assertPreConditions(): void
    {
    }

    protected function nonExistentInParent(): void
    {
    }
}

namespace App\Tests;

use PHPUnit\Framework\TestCase;

final class OutsideScopeNonOverrideFixtureTest extends TestCase
{
    protected function customMethod(): void
    {
    }
}
