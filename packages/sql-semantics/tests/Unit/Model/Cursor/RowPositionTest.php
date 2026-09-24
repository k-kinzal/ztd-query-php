<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Cursor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Cursor\RowPosition;
use SqlSemantics\Model\Statement\Cursor\FetchCursorStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RowPosition::class)]
#[Medium]
final class RowPositionTest extends TestCase
{
    public function testRepresentsEverySingleRowPosition(): void
    {
        self::assertSame(['NEXT', 'PRIOR', 'FIRST', 'LAST'], array_column(RowPosition::cases(), 'value'));
    }

    #[TestWith(['FETCH LAST FROM cur', RowPosition::Last, 'FETCH LAST FROM "cur"'])]
    #[TestWith(['FETCH FIRST IN cur', RowPosition::First, 'FETCH FIRST FROM "cur"'])]
    #[TestWith(['FETCH BACKWARD FROM cur', RowPosition::Prior, 'FETCH PRIOR FROM "cur"'])]
    #[TestWith(['FETCH FORWARD FROM cur', RowPosition::Next, 'FETCH NEXT FROM "cur"'])]
    public function testNormalizesSingleRowScansToAPosition(string $sql, RowPosition $position, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind($sql);
        self::assertInstanceOf(FetchCursorStatement::class, $statement);
        self::assertSame($position, $statement->movement);
        self::assertSame($expected, $statement->toString());
        self::assertSame($expected, $binder->bind($expected)->toString());
    }
}
