<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index\Btree;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\From\ColumnKind;
use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\LogicalOperator;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\Binding\IndexTarget;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\CreateIndex;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\IndexBooleanPredicate;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\IndexCondition;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\IndexKey;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\NullIndexPredicate;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\IndexHeading;

#[CoversClass(IndexCondition::class)]
#[Small]
final class IndexConditionTest extends TestCase
{
    public function testBindingsKeepsBothBoundBranches(): void
    {
        $table = new IndexHeading(new IndexTarget(new Identifier('app'), new Identifier('orders')), [new Identifier('id'), new Identifier('second')], [ColumnKind::Int4, ColumnKind::Int4]);
        $predicate = new IndexBooleanPredicate(
            LogicalOperator::And,
            new NullIndexPredicate(0),
            new IndexBooleanPredicate(LogicalOperator::Not, new NullIndexPredicate(1))
        );

        $index = new CreateIndex($table, new Identifier('idx'), [new IndexKey(0)], predicate: $predicate);

        self::assertSame($predicate, $index->predicate);
    }

    public function testSqlUsesAnIterativeTraversalForDeepTrees(): void
    {
        $table = new IndexHeading(new IndexTarget(new Identifier('app'), new Identifier('orders')), [new Identifier('Key"1'), new Identifier('second')], [ColumnKind::Int4, ColumnKind::Int4]);
        $predicate = array_reduce(
            range(1, 4000),
            static fn (IndexBooleanPredicate $child): IndexBooleanPredicate => new IndexBooleanPredicate(LogicalOperator::Not, $child),
            new IndexBooleanPredicate(LogicalOperator::Or, new NullIndexPredicate(1, true), new NullIndexPredicate(0)),
        );
        $index = new CreateIndex($table, new Identifier('idx'), [new IndexKey(0)], predicate: $predicate);
        $sql = $index->toString();

        self::assertStringEndsWith(' WHERE ' . str_repeat('(NOT ', 4000) . '(("second" IS NOT NULL) OR ("Key""1" IS NULL))' . str_repeat(')', 4000), $sql);
    }
}
