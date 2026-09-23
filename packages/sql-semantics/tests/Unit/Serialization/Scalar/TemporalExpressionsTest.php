<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Scalar;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Scalar\TemporalExpressions;

#[CoversClass(TemporalExpressions::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class TemporalExpressionsTest extends TestCase
{
    public function testWritePreservesOperandRoles(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $query = $binder->bind('SELECT EXTRACT(YEAR FROM CURRENT_TIMESTAMP)');
        self::assertSame('SELECT EXTRACT(YEAR FROM CURRENT_TIMESTAMP)', $query->toString());
        self::assertSame('SELECT EXTRACT(YEAR FROM CURRENT_TIMESTAMP)', $binder->bind($query->toString())->toString());
    }

    public function testWritePreservesTheBindingOrderOfLeadingIntervalParameters(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $query = $binder->bind('SELECT INTERVAL ? DAY + ?, ? - INTERVAL ? HOUR');
        self::assertSame('SELECT (INTERVAL ? DAY + ?), DATE_SUB(?, INTERVAL ? HOUR)', $query->toString());
        self::assertSame($query->toString(), $binder->bind($query->toString())->toString());
    }
}
