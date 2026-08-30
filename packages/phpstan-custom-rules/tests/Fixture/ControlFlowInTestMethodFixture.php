<?php

declare(strict_types=1);

namespace Tests\Unit\Fixture;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ControlFlowInTestMethodFixtureTest extends TestCase
{
    public function testWithIf(): void
    {
        if (true) {
            self::assertTrue(true);
        }
    }

    public function testWithFor(): void
    {
        for ($i = 0; $i < 3; $i++) {
            self::assertTrue(true);
        }
    }

    public function testWithForeach(): void
    {
        foreach ([1, 2, 3] as $value) {
            self::assertIsInt($value);
        }
    }

    public function testWithWhile(): void
    {
        while (false) {
            self::assertTrue(true);
        }
    }

    public function testWithDoWhile(): void
    {
        do {
            self::assertTrue(true);
        } while (false);
    }

    public function testWithSwitch(): void
    {
        switch (1) {
            case 1:
                self::assertTrue(true);
                break;
        }
    }

    public function testWithMatch(): void
    {
        $result = match (1) {
            1 => 'one',
            default => 'other',
        };
        self::assertSame('one', $result);
    }

    public function testWithTryCatch(): void
    {
        try {
            self::assertTrue(true);
        } catch (\Exception $e) {
            self::fail($e->getMessage());
        }
    }

    public function testWithClosureContainingIf(): void
    {
        $fn = static function (): bool {
            if (true) {
                return true;
            }
            return false;
        };
        self::assertTrue($fn());
    }

    public function testWithArrowFunction(): void
    {
        $fn = static fn (): bool => true;
        self::assertTrue($fn());
    }

    public function testCleanNoControlFlow(): void
    {
        $value = 1 + 2;
        self::assertSame(3, $value);
    }

    #[Test]
    public function attributeTestWithIf(): void
    {
        if (true) {
            self::assertTrue(true);
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        if (true) {
            return;
        }
    }
}

namespace Tests\Integration\Fixture;

use PHPUnit\Framework\TestCase;

final class IntegrationControlFlowFixtureTest extends TestCase
{
    public function testIntegrationWithIf(): void
    {
        if (true) {
            self::assertTrue(true);
        }
    }
}

namespace App\Tests;

use PHPUnit\Framework\TestCase;

final class OutsideScopeControlFlowFixtureTest extends TestCase
{
    public function testAllowedIfOutsideScope(): void
    {
        if (true) {
            self::assertTrue(true);
        }
    }
}
