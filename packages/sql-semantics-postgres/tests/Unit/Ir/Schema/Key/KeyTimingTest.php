<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Key;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Key\KeyTiming;

#[CoversClass(KeyTiming::class)]
#[Small]
final class KeyTimingTest extends TestCase
{
    public function testBackedValuesDistinguishAllNativeTimingRoles(): void
    {
        self::assertSame(
            ['NOT DEFERRABLE', 'DEFERRABLE INITIALLY IMMEDIATE', 'DEFERRABLE INITIALLY DEFERRED'],
            array_column(KeyTiming::cases(), 'value')
        );
    }
}
