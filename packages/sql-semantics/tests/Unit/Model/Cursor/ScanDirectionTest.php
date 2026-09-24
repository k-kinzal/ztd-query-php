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
use SqlSemantics\Model\Cursor\ScanDirection;
use SqlSemantics\Model\Statement\Cursor\MoveCursorStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ScanDirection::class)]
#[Medium]
final class ScanDirectionTest extends TestCase
{
    public function testRepresentsBothScanDirections(): void
    {
        self::assertSame(['FORWARD', 'BACKWARD'], array_column(ScanDirection::cases(), 'value'));
    }

    #[TestWith(['MOVE 4 FROM cur', ScanDirection::Forward])]
    #[TestWith(['MOVE FORWARD 4 FROM cur', ScanDirection::Forward])]
    #[TestWith(['MOVE BACKWARD 4 FROM cur', ScanDirection::Backward])]
    public function testDefaultsToAForwardScan(string $sql, ScanDirection $direction): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql);
        self::assertInstanceOf(MoveCursorStatement::class, $statement);
        self::assertInstanceOf(CountedRows::class, $statement->movement);
        self::assertSame($direction, $statement->movement->direction);
    }
}
