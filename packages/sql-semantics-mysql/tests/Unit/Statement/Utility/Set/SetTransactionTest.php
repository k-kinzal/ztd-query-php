<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Utility\Set;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\AccessMode;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetTransaction;
use SqlSemantics\Platform\MySql\Statement\Variable\VariableScope;

#[CoversClass(SetTransaction::class)]
#[Medium]
final class SetTransactionTest extends TestCase
{
    public function testDeriveStatementDerivesNothing(): void
    {
        $set = (new Semantics(Dialect::MySql))->analyze('SET PERSIST_ONLY TRANSACTION ISOLATION LEVEL READ UNCOMMITTED');
        self::assertInstanceOf(SetTransaction::class, $set->statement);
        self::assertSame(VariableScope::PersistOnly, $set->statement->scope);
        self::assertNull($set->shape());
    }

    public function testRenderKeepsTheOrder(): void
    {
        self::assertSame('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('set transaction isolation level repeatable read, read only')->toString());
    }

    public function testRefusesTwoCharacteristicsOfOneKind(): void
    {
        $this->expectExceptionMessage('SET TRANSACTION writes at most one characteristic of each kind.');
        new SetTransaction([AccessMode::ReadOnly, AccessMode::ReadWrite]);
    }
}
