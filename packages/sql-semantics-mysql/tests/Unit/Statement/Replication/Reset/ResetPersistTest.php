<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Replication\Reset;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetPersist;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Operation;

#[CoversClass(ResetPersist::class)]
#[Medium]
final class ResetPersistTest extends TestCase
{
    public function testDeriveStatementRejectsMySql57(): void
    {
        $this->expectExceptionMessage('RESET PERSIST needs MySQL 8.0 or later.');

        new Operation((new Semantics(Dialect::MySql, 'mysql-5.7.44'))->context([]), new ResetPersist());
    }

    public function testRenderWritesTheComponentVariable(): void
    {
        $reset = (new Semantics(Dialect::MySql))->analyze('reset persist comp.var');

        self::assertSame('RESET PERSIST comp.var', $reset->toString());
        self::assertEquals(new ResetPersist(false, new Name('var'), new Name('comp')), $reset->statement);
    }
}
