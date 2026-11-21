<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Query;

#[CoversClass(Query::class)]
#[Small]
final class QueryTest extends TestCase
{
    public function testToStringIsAvailableOnTheContract(): void
    {
        $value = new class () implements Query {
            public function toString(): string
            {
                return 'SELECT 1';
            }
        };

        self::assertSame('SELECT 1', $value->toString());
    }
}
