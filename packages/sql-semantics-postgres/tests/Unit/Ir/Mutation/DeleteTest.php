<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Mutation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Mutation\Delete;
use SqlSemantics\Platform\PostgreSql\Ir\Mutation\ReturnedColumn;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerComparison;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\IntegerOperator;
use SqlSemantics\Platform\PostgreSql\Ir\TableSource;

#[CoversClass(Delete::class)]
#[Small]
final class DeleteTest extends TestCase
{
    public function testToStringRetainsTheAbsoluteTargetAndOrderedPredicate(): void
    {
        $source = new TableSource(new Identifier('public'), new Identifier('accounts'), [new Identifier('id')], true);
        $where = new IntegerComparison(IntegerOperator::Less, new ColumnReference(0), new IntegerLiteral('2'));

        self::assertSame('DELETE FROM ONLY "public"."accounts" WHERE ("id" OPERATOR(pg_catalog.<) CAST(2 AS pg_catalog.int4))', (new Delete($source, $where))->toString());
    }

    public function testProfileIdUsesTheNativeDialectRelease(): void
    {
        $source = new TableSource(new Identifier('public'), new Identifier('accounts'), []);

        self::assertStringStartsWith('pg-', (new Delete($source))->profileId());
    }

    public function testToStringReturnsAFieldFromEachDeletedRow(): void
    {
        $target = new TableSource(new Identifier('public'), new Identifier('accounts'), [new Identifier('id')]);
        $field = new ReturnedColumn(new ColumnReference(0), new Identifier('removed'));
        $delete = new Delete($target, returning: [$field]);

        self::assertSame([$field], $delete->returning);
        self::assertSame('DELETE FROM "public"."accounts" RETURNING "id" AS "removed"', $delete->toString());
    }

    public function testRejectsAnOutOfScopePredicateSlot(): void
    {
        $source = new TableSource(new Identifier('public'), new Identifier('accounts'), [new Identifier('id')]);
        $this->expectExceptionMessage('A DELETE predicate slot must belong to its target.');

        new Delete($source, new IntegerComparison(IntegerOperator::Less, new ColumnReference(1), new IntegerLiteral('2')));
    }
}
