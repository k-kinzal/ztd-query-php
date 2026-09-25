<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Cursor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Cursor\IntegerOffset;
use SqlSemantics\Model\Cursor\OffsetOrigin;
use SqlSemantics\Model\Cursor\PositionedRow;
use SqlSemantics\Model\Statement\Cursor\MoveCursorStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(PositionedRow::class)]
#[Medium]
final class PositionedRowTest extends TestCase
{
    public function testRetainsTheOriginAndTheExactOffset(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('MOVE RELATIVE 0 FROM cur');
        self::assertInstanceOf(MoveCursorStatement::class, $statement);
        $movement = $statement->movement;
        self::assertInstanceOf(PositionedRow::class, $movement);
        self::assertSame(OffsetOrigin::Relative, $movement->origin);
        self::assertSame('0', $movement->offset->text);
        self::assertSame('MOVE RELATIVE 0 FROM "cur"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testExposesTheSuppliedOperands(): void
    {
        $offset = new IntegerOffset('-2');
        $movement = new PositionedRow(OffsetOrigin::Absolute, $offset);
        self::assertSame(OffsetOrigin::Absolute, $movement->origin);
        self::assertSame($offset, $movement->offset);
    }
}
