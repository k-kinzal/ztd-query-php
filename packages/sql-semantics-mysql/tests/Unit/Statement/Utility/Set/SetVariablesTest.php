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
        self::assertInstanceOf(SetVariables::class, $set);
        self::assertSame(VariableScope::Persist, $set->scopeOf(1));
        self::assertSame(VariableScope::Session, $set->scopeOf(2));
        self::assertNull($set->scopeOf(3));
        self::assertSame(VariableScope::Persist, $set->scopeOf(4));
    }

    public function testScopeOfRefusesAPositionOutsideTheItems(): void
    {
        $set = (new Semantics(Dialect::MySql))->analyze('SET a = 1');
        self::assertInstanceOf(SetVariables::class, $set);
        $this->expectExceptionMessage('The position is one of the items.');
        $set->scopeOf(1);
    }

    public function testDeriveStatementDerivesEveryItem(): void
    {
        $set = (new Semantics(Dialect::MySql))->analyze('SET @a = b, @@sort_buffer_size = c', []);
        self::assertInstanceOf(SetVariables::class, $set);
        self::assertCount(1, $set->diagnostics());
        self::assertNull($set->shape());
    }

    public function testRenderWritesTheItemsInOrder(): void
    {
        self::assertSame('SET LOCAL a = 1, @b = 2', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('set local a = 1, @b := 2')->toString());
    }

    public function testRefusesAnEmptyList(): void
    {
        $this->expectExceptionMessage('SET assigns at least one item.');
        new SetVariables([]);
    }
}
