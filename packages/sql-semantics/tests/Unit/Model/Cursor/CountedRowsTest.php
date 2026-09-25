<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Cursor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Cursor\CountedRows;
use SqlSemantics\Model\Cursor\IntegerOffset;
use SqlSemantics\Model\Cursor\ScanDirection;
use SqlSemantics\Model\Statement\Cursor\FetchCursorStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CountedRows::class)]
#[Medium]
final class CountedRowsTest extends TestCase
{
    #[TestWith(['FETCH BACKWARD 2 IN cur', ScanDirection::Backward, '2', 'FETCH BACKWARD 2 FROM "cur"'])]
    #[TestWith(['FETCH -5 FROM cur', ScanDirection::Forward, '-5', 'FETCH FORWARD -5 FROM "cur"'])]
    #[TestWith(['FETCH FORWARD 3 FROM cur', ScanDirection::Forward, '3', 'FETCH FORWARD 3 FROM "cur"'])]
    public function testRetainsTheDirectionAndTheUnevaluatedCount(string $sql, ScanDirection $direction, string $count, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind($sql);
        self::assertInstanceOf(FetchCursorStatement::class, $statement);
        $movement = $statement->movement;
        self::assertInstanceOf(CountedRows::class, $movement);
        self::assertSame($direction, $movement->direction);
        self::assertSame($count, $movement->count->text);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($expected)));
    }

    public function testExposesTheSuppliedOperands(): void
    {
        $count = new IntegerOffset('0x1F');
        $movement = new CountedRows(ScanDirection::Backward, $count);
        self::assertSame(ScanDirection::Backward, $movement->direction);
        self::assertSame($count, $movement->count);
    }
}
