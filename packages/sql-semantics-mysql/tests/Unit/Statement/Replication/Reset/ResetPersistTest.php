<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Replication\Reset;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Statement\Replication\Reset\ResetPersist;
use SqlSemantics\Testing\MySql\InspectedStatement;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(ResetPersist::class)]
#[Medium]
final class ResetPersistTest extends TestCase
{
    public function testDeriveStatementRejectsMySql57(): void
    {
        $this->expectExceptionMessage('RESET PERSIST needs MySQL 8.0 or later.');

        new InspectedStatement((new Inspection('mysql-5.7.44'))->context([]), new ResetPersist());
    }

    public function testRenderWritesTheComponentVariable(): void
    {
        $reset = (new Inspection())->inspect('reset persist comp.var');

        self::assertSame('RESET PERSIST comp.var', $reset->toString());
        self::assertEquals(new ResetPersist(false, new Name('var'), new Name('comp')), $reset->statement);
    }
}
