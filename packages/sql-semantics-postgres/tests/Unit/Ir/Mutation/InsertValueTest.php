<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Mutation\InsertValue;

#[CoversClass(InsertValue::class)]
#[Small]
final class InsertValueTest extends TestCase
{
    public function testToStringDistinguishesIntegerAndNull(): void
    {
        self::assertSame('CAST(-2 AS pg_catalog.int4)', (new InsertValue(new IntegerLiteral('-2')))->toString());
        self::assertSame('NULL', (new InsertValue(null))->toString());
    }
}
