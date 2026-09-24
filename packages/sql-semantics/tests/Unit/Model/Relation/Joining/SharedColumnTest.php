<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Relation\Joining;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Relation\Joining\SharedColumn;
use SqlSemantics\Model\Relation\Joining\UsingJoin;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SharedColumn::class)]
#[Medium]
final class SharedColumnTest extends TestCase
{
    public function testPairsBothInputsWithTheMergedOutput(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER NOT NULL, n INTEGER)');
        $query = (new Binder($schema))->bind('SELECT id FROM t a RIGHT JOIN t b USING (id)');
        self::assertInstanceOf(BoundSelect::class, $query);
        $join = $query->from;
        self::assertInstanceOf(UsingJoin::class, $join);
        $column = $join->columns[0];
        self::assertSame('id', $column->name);
        self::assertSame('r0', $column->left->columnBinding()?->relationId);
        self::assertSame('r1', $column->right->columnBinding()?->relationId);
        self::assertSame('id', $column->output->columnBinding()?->column->name);
        self::assertSame($column->output->type, $query->outputs[0]->expression->type);
    }

    public function testRejectsAnEmptyName(): void
    {
        $value = Expression::literal(1, Dialect::PostgreSql);
        $this->expectException(InvalidStructure::class);
        new SharedColumn('', $value, $value, $value);
    }

    public function testRejectsInputsFromDifferentDialects(): void
    {
        $left = Expression::literal(1, Dialect::PostgreSql);
        $right = Expression::literal(1, Dialect::MySql);
        $this->expectException(InvalidStructure::class);
        new SharedColumn('id', $left, $right, $left);
    }
}
