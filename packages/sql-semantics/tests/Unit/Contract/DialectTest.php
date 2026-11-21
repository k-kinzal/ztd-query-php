<?php

declare(strict_types=1);

namespace Tests\Unit\Contract;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\Dialect;

#[CoversClass(Dialect::class)]
#[Small]
final class DialectTest extends TestCase
{
    public function testDatabaseIsAvailableOnTheContract(): void
    {
        $value = new class () implements Dialect {
            public function database(): string
            {
                return 'sqlite';
            }
        };

        self::assertSame('sqlite', $value->database());
    }
}
