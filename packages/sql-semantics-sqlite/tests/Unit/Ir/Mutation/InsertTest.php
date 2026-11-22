<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Mutation;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\ColumnReference;
use SqlSemantics\Platform\Sqlite\Ir\Identifier;
use SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Ir\Mutation\Insert;
use SqlSemantics\Platform\Sqlite\Ir\Mutation\InsertConflict;
use SqlSemantics\Platform\Sqlite\Ir\Mutation\ReturnedColumn;
use SqlSemantics\Platform\Sqlite\Ir\ResultColumn;
use SqlSemantics\Platform\Sqlite\Ir\Rows\ValueRow;
use SqlSemantics\Platform\Sqlite\Ir\Rows\ValuesQuery;
use SqlSemantics\Platform\Sqlite\Ir\Select;
use SqlSemantics\Platform\Sqlite\Ir\TableSource;

/**
 * @covers \SqlSemantics\Platform\Sqlite\Ir\Mutation\Insert
 */
final class InsertTest extends TestCase
{
    public function testProfileIdMatchesTheInputProfile(): void
    {
        $rows = new ValuesQuery([new ValueRow([new IntegerLiteral('3')])]);
        $target = new TableSource(new Identifier('main'), new Identifier('records'), [new Identifier('id')]);

        self::assertSame($rows->profileId(), (new Insert($target, [new ColumnReference(0)], $rows))->profileId());
    }

    public function testToStringPreservesTargetOrderAndExplicitConflictPolicy(): void
    {
        $rows = new ValuesQuery([new ValueRow([new IntegerLiteral('3'), new IntegerLiteral('4')])]);
        $target = new TableSource(new Identifier('temp'), new Identifier('records'), [new Identifier('a'), new Identifier('b')]);
        $insert = new Insert($target, [new ColumnReference(1), new ColumnReference(0)], $rows, InsertConflict::Abort);

        self::assertSame('INSERT OR ABORT INTO "temp"."records" ("b", "a") VALUES (3, 4)', $insert->toString());
    }

    public function testToStringUsesTheCheckedProjectionInput(): void
    {
        $target = new TableSource(new Identifier('main'), new Identifier('items'), [new Identifier('id'), new Identifier('computed')]);
        $query = new Select([new ResultColumn(new IntegerLiteral('7'), new Identifier('answer'))]);
        $return = new ReturnedColumn(new ColumnReference(1), new Identifier('result'));

        $insert = new Insert($target, [new ColumnReference(0)], $query, returning: [$return]);

        self::assertSame('INSERT INTO "main"."items" ("id") SELECT 7 AS "answer" RETURNING "computed" AS "result"', $insert->toString());
    }
}
