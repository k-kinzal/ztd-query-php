<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Rows;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Rows\LocalDefinition;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Relation\DerivedTable;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(LocalDefinition::class)]
#[Medium]
final class LocalDefinitionTest extends TestCase
{
    public function testResolveReturnsTheImmediateDerivedDefinition(): void
    {
        $query = (new Inspection())->inspect('SELECT q.a FROM (SELECT 1 AS a) AS q');
        self::assertInstanceOf(Select::class, $query->statement);
        self::assertInstanceOf(DerivedTable::class, $query->statement->from);

        self::assertSame($query->statement->from->query, (new LocalDefinition())->resolve($query->statement->from));
    }

    public function testConsumerReturnsTheCheckedOuterSelect(): void
    {
        $query = (new Inspection())->inspect('SELECT q.a FROM (SELECT 1 AS a) AS q');
        self::assertInstanceOf(Select::class, $query->statement);

        self::assertSame($query->statement, (new LocalDefinition())->consumer($query->statement));
    }

    public function testColumnsFollowTheResolvedFieldOrder(): void
    {
        $query = (new Inspection())->inspect('SELECT q.b AS result FROM (SELECT 1 AS a, 2 AS b) AS q');
        self::assertInstanceOf(Select::class, $query->statement);
        self::assertInstanceOf(DerivedTable::class, $query->statement->from);

        $columns = (new LocalDefinition())->columns($query->binding(), $query->statement, $query->statement->from);

        self::assertCount(1, $columns);
        self::assertSame(1, $columns[0]->slot);
        self::assertSame('result', $columns[0]->name->value);
    }

    public function testQueryBlockAllowsASeparatePredicate(): void
    {
        $query = (new Inspection())->inspect('SELECT q.a FROM (SELECT 1 AS a) AS q WHERE TRUE');
        self::assertInstanceOf(Select::class, $query->statement);

        self::assertSame($query->statement, (new LocalDefinition())->queryBlock($query->statement));
    }
}
