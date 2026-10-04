<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Set;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables;
use SqlSemantics\Platform\MySql\Statement\Variable\VariableScope;

#[CoversClass(SetVariables::class)]
#[Medium]
final class SetVariablesTest extends TestCase
{
    public function testScopeOfAnswersTheInheritedScope(): void
    {
        $set = (new Semantics(Dialect::MySql))->analyze('SET PERSIST a = 1, b = 2, @@SESSION.c = 3, @d = 4, e = 5');
        self::assertInstanceOf(SetVariables::class, $set->statement);
        self::assertSame(VariableScope::Persist, $set->statement->scopeOf(1));
        self::assertSame(VariableScope::Session, $set->statement->scopeOf(2));
        self::assertNull($set->statement->scopeOf(3));
        self::assertSame(VariableScope::Persist, $set->statement->scopeOf(4));
    }

    public function testScopeOfRefusesAPositionOutsideTheItems(): void
    {
        $set = (new Semantics(Dialect::MySql))->analyze('SET a = 1');
        self::assertInstanceOf(SetVariables::class, $set->statement);
        $this->expectExceptionMessage('The position is one of the items.');
        $set->statement->scopeOf(1);
    }

    public function testDeriveStatementDerivesEveryItem(): void
    {
        $set = (new Semantics(Dialect::MySql))->analyze('SET @a = b, @@x = c', []);
        self::assertInstanceOf(SetVariables::class, $set->statement);
        self::assertCount(1, $set->facts->diagnostics);
        self::assertNull($set->shape());
    }

    public function testRenderWritesTheItemsInOrder(): void
    {
        self::assertSame('SET SESSION a = 1, @b = 2', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('set local a = 1, @b := 2')->toString());
    }

    public function testRefusesAnEmptyList(): void
    {
        $this->expectExceptionMessage('SET assigns at least one item.');
        new SetVariables([]);
    }
}
