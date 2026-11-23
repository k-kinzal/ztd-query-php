<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\From\ColumnKind;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Mutation\Insert;
use SqlSemantics\Platform\PostgreSql\Ir\Mutation\Insert\Destination;
use SqlSemantics\Platform\PostgreSql\Ir\Mutation\Insert\WriteSlot;
use SqlSemantics\Platform\PostgreSql\Ir\Mutation\InsertCell;
use SqlSemantics\Platform\PostgreSql\Ir\Mutation\InsertRow;
use SqlSemantics\Platform\PostgreSql\Ir\Mutation\ReturnedColumn;
use SqlSemantics\Platform\PostgreSql\Ir\TableSource;

#[CoversClass(Insert::class)]
#[Small]
final class InsertTest extends TestCase
{
    public function testToStringKeepsTargetColumnAndRowOrder(): void
    {
        $relation = new TableSource(new Identifier('public'), new Identifier('t'), [new Identifier('a'), new Identifier('b')]);
        $target = new Destination($relation, [new WriteSlot(1, ColumnKind::Int4), new WriteSlot(0, ColumnKind::Int4)]);
        $row = new InsertRow([new InsertCell(null), new InsertCell(new IntegerLiteral('-2'))]);
        $value = new Insert($target, [$row, $row]);

        self::assertSame('INSERT INTO "public"."t" ("b", "a") VALUES (NULL, CAST(-2 AS pg_catalog.int4)), (NULL, CAST(-2 AS pg_catalog.int4))', $value->toString());
    }

    public function testProfileIdBeginsWithTheNativeGrammarRelease(): void
    {
        $relation = new TableSource(new Identifier('public'), new Identifier('t'), [new Identifier('a')]);
        $target = new Destination($relation, [new WriteSlot(0, ColumnKind::Int4)]);
        $row = new InsertRow([new InsertCell(null)]);

        self::assertStringStartsWith('pg-17.2/', (new Insert($target, [$row]))->profileId());
    }

    public function testRejectsAWidthThatDoesNotMatchTheTarget(): void
    {
        $relation = new TableSource(new Identifier('public'), new Identifier('t'), [new Identifier('b'), new Identifier('a')]);
        $target = new Destination($relation, [new WriteSlot(0, ColumnKind::Int4), new WriteSlot(1, ColumnKind::Int4)]);
        $row = new InsertRow([new InsertCell(null)]);
        $this->expectExceptionMessage('must match the target width');

        new Insert($target, [$row]);
    }

    public function testToStringReturnsAFieldOutsideTheWriteList(): void
    {
        $relation = new TableSource(new Identifier('public'), new Identifier('t'), [new Identifier('b'), new Identifier('a')]);
        $target = new Destination($relation, [new WriteSlot(1, ColumnKind::Int4)]);
        $row = new InsertRow([new InsertCell(null)]);
        $returning = [new ReturnedColumn(new ColumnReference(0), new Identifier('filled'))];

        self::assertSame(
            'INSERT INTO "public"."t" ("a") VALUES (NULL) RETURNING "b" AS "filled"',
            (new Insert($target, [$row], returning: $returning))->toString()
        );
    }
}
