<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\From;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\From\DerivedBinding;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Column\ResolvedColumn;
use SqlSemantics\Platform\PostgreSql\Statement\Query\ExpressionTarget;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Select;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\DerivedTable;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\Join;
use SqlSemantics\Testing\PostgreSql\Inspection;

#[CoversClass(DerivedBinding::class)]
#[Medium]
final class DerivedBindingTest extends TestCase
{
    public function testResolvedUsesTheExactBoundOrdinal(): void
    {
        $operation = (new Inspection())->inspect('SELECT r.y FROM (SELECT 1 AS x) l CROSS JOIN (SELECT 2 AS x, 3 AS y) r');
        $statement = $operation->statement;
        self::assertInstanceOf(Select::class, $statement);
        self::assertInstanceOf(Join::class, $statement->from);
        $right = $statement->from->right;
        self::assertInstanceOf(DerivedTable::class, $right);
        $column = $statement->targets[0];
        self::assertInstanceOf(ExpressionTarget::class, $column);
        $bound = $operation->binding();
        $binding = new DerivedBinding($bound, $right);

        $resolution = $bound->facts->scalar($column->expression)->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $resolution);
        self::assertSame(1, $binding->resolved($resolution)->slot);
    }

    public function testRelationRetainsIdentityDuringBinding(): void
    {
        $operation = (new Inspection())->inspect('SELECT q.x FROM (SELECT 3 AS x) q');
        self::assertInstanceOf(Select::class, $operation->statement);
        $input = $operation->statement->from;
        self::assertInstanceOf(DerivedTable::class, $input);

        self::assertSame($input, (new DerivedBinding($operation->binding(), $input))->relation());
    }

    public function testSourceExportsOrderedSignedFields(): void
    {
        $operation = (new Inspection())->inspect('SELECT q.y FROM (SELECT 3 AS x, -9 AS y) q');
        self::assertInstanceOf(Select::class, $operation->statement);
        $input = $operation->statement->from;
        self::assertInstanceOf(DerivedTable::class, $input);
        $value = (new DerivedBinding($operation->binding(), $input))->source();

        self::assertSame(['x', 'y'], array_map(static fn ($column): string => $column->name->value, $value->definition->columns));
    }
}
