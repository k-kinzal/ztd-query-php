<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Mutation\Insert\Destination;
use SqlSemantics\Platform\PostgreSql\Ir\Mutation\InsertQuery;
use SqlSemantics\Platform\PostgreSql\Ir\Mutation\ReturnedColumn;
use SqlSemantics\Platform\PostgreSql\Ir\ResultColumn;
use SqlSemantics\Platform\PostgreSql\Ir\Select;
use SqlSemantics\Platform\PostgreSql\Ir\TableSource;

#[CoversClass(InsertQuery::class)]
#[Small]
final class InsertQueryTest extends TestCase
{
    public function testToStringKeepsTheOwnedQuery(): void
    {
        $relation = new TableSource(new Identifier('app'), new Identifier('events'), [new Identifier('id'), new Identifier('stamp')]);
        $target = new Destination($relation, [new ColumnReference(0)]);
        $select = new Select([new ResultColumn(new IntegerLiteral('7'), new Identifier('n'))]);
        $returning = [new ReturnedColumn(new ColumnReference(1), new Identifier('saved'))];
        $insert = new InsertQuery($target, $select, $returning);

        self::assertSame('INSERT INTO "app"."events" ("id") ' . $select->toString() . ' RETURNING "stamp" AS "saved"', $insert->toString());
    }

    public function testProfileIdUsesTheSourceProfile(): void
    {
        $relation = new TableSource(new Identifier('app'), new Identifier('events'), [new Identifier('id')]);
        $target = new Destination($relation, [new ColumnReference(0)]);
        $select = new Select([new ResultColumn(new IntegerLiteral('1'), new Identifier('n'))]);

        self::assertSame($select->profileId(), (new InsertQuery($target, $select))->profileId());
    }
}
