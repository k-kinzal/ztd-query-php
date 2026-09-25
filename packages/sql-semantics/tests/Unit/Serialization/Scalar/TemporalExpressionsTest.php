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
        self::assertSame('SELECT EXTRACT(YEAR FROM CURRENT_TIMESTAMP)', (new \SqlSemantics\SimpleSerializer())->serialize($query));
        self::assertSame('SELECT EXTRACT(YEAR FROM CURRENT_TIMESTAMP)', (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($query))));
    }

    public function testWritePreservesTheBindingOrderOfLeadingIntervalParameters(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $query = $binder->bind('SELECT INTERVAL ? DAY + ?, ? - INTERVAL ? HOUR');
        self::assertSame('SELECT (INTERVAL ? DAY + ?), DATE_SUB(?, INTERVAL ? HOUR)', (new \SqlSemantics\SimpleSerializer())->serialize($query));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($query), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($query))));
    }

    public function testWriteKeepsPeriodsAndZoneConversions(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $query = $binder->bind("SELECT (1, 2) OVERLAPS (3, 4), LOCALTIMESTAMP AT TIME ZONE 'UTC', LOCALTIMESTAMP AT LOCAL");
        self::assertSame("SELECT ((1, 2) OVERLAPS(3, 4)), (LOCALTIMESTAMP AT TIME ZONE 'UTC'), (LOCALTIMESTAMP AT LOCAL)", (new \SqlSemantics\SimpleSerializer())->serialize($query));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($query), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($query))));
    }

    public function testWriteSpellsGetFormatWithItsKindKeyword(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $query = $binder->bind("SELECT GET_FORMAT(TIMESTAMP, 'EUR')");
        self::assertSame("SELECT GET_FORMAT(DATETIME, 'EUR')", (new \SqlSemantics\SimpleSerializer())->serialize($query));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($query), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($query))));
    }
}
