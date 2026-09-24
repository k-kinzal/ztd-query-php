<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Cursor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Cursor\RemainingRows;
use SqlSemantics\Model\Cursor\ScanDirection;
use SqlSemantics\Model\Statement\Cursor\FetchCursorStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RemainingRows::class)]
#[Medium]
final class RemainingRowsTest extends TestCase
{
    #[TestWith(['FETCH ALL FROM cur', ScanDirection::Forward, 'FETCH FORWARD ALL FROM "cur"'])]
    #[TestWith(['FETCH BACKWARD ALL IN cur', ScanDirection::Backward, 'FETCH BACKWARD ALL FROM "cur"'])]
    public function testRetainsTheScanDirectionWithoutACount(string $sql, ScanDirection $direction, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind($sql);
        self::assertInstanceOf(FetchCursorStatement::class, $statement);
        $movement = $statement->movement;
        self::assertInstanceOf(RemainingRows::class, $movement);
        self::assertSame($direction, $movement->direction);
        self::assertSame($expected, $statement->toString());
        self::assertSame($expected, $binder->bind($expected)->toString());
    }

    public function testExposesTheSuppliedDirection(): void
    {
        self::assertSame(ScanDirection::Backward, (new RemainingRows(ScanDirection::Backward))->direction);
    }
}
