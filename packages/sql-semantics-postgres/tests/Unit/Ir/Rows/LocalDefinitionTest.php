<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Rows;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\LocalDefinition;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Select;
use SqlSemantics\Platform\PostgreSql\Statement\Relation\DerivedTable;
use SqlSemantics\Testing\PostgreSql\Inspection;

#[CoversClass(LocalDefinition::class)]
#[Medium]
final class LocalDefinitionTest extends TestCase
{
    public function testResolveReturnsTheImmediateDerivedDefinition(): void
    {
        $query = (new Inspection())->inspect('SELECT q.a FROM (SELECT 1 AS a) AS q');
        self::assertInstanceOf(Select::class, $query->statement);
        self::assertInstanceOf(DerivedTable::class, $query->statement->from);

        self::assertSame($query->statement->from->query, (new LocalDefinition())->resolve($query->statement));
    }

    public function testConsumerReturnsTheCheckedOuterSelect(): void
    {
        $query = (new Inspection())->inspect('SELECT q.a FROM (SELECT 1 AS a) AS q');
        self::assertInstanceOf(Select::class, $query->statement);

        self::assertSame($query->statement, (new LocalDefinition())->consumer($query->statement));
    }

    public function testQueryBlockAllowsASeparatePredicate(): void
    {
        $query = (new Inspection())->inspect('SELECT q.a FROM (SELECT 1 AS a) AS q WHERE TRUE');
        self::assertInstanceOf(Select::class, $query->statement);

        self::assertSame($query->statement, (new LocalDefinition())->queryBlock($query->statement));
    }
}
