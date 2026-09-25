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
use SqlSemantics\Model\Cursor\Movement;
use SqlSemantics\Model\Cursor\PositionedRow;
use SqlSemantics\Model\Cursor\RemainingRows;
use SqlSemantics\Model\Cursor\RowPosition;
use SqlSemantics\Model\Statement\Cursor\FetchCursorStatement;
use SqlSemantics\Model\Statement\Cursor\MoveCursorStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Movement::class)]
#[Medium]
final class MovementTest extends TestCase
{
    /**
     * @param class-string<Movement> $class
     */
    #[TestWith(['FETCH LAST FROM cur', RowPosition::class, 'FETCH LAST FROM "cur"'])]
    #[TestWith(['FETCH FORWARD 3 FROM cur', CountedRows::class, 'FETCH FORWARD 3 FROM "cur"'])]
    #[TestWith(['FETCH BACKWARD ALL FROM cur', RemainingRows::class, 'FETCH BACKWARD ALL FROM "cur"'])]
    #[TestWith(['MOVE ABSOLUTE -2 FROM cur', PositionedRow::class, 'MOVE ABSOLUTE -2 FROM "cur"'])]
    public function testClassifiesEveryCursorMovementForm(string $sql, string $class, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind($sql);
        self::assertTrue($statement instanceof FetchCursorStatement || $statement instanceof MoveCursorStatement);
        self::assertInstanceOf($class, $statement->movement);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($expected)));
    }
}
