<?php

declare(strict_types=1);

namespace Tests\Unit\Lowering\Account\Privilege;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Lowering\Account\Privilege\RevokeRule;
use SqlSemantics\Platform\MySql\Statement as M;

#[CoversClass(RevokeRule::class)]
#[Medium]
final class RevokeRuleTest extends TestCase
{
    public function testStatementRetainsTheLegacyGlobalAllInstruction(): void
    {
        $sql = 'CREATE PROCEDURE p() REVOKE ALL ON FUNCTION *.* FROM u';
        $legacy = (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze($sql)->statement;
        $modern = (new Semantics(Dialect::MySql, 'mysql-8.4.7'))->analyze($sql)->statement;
        self::assertInstanceOf(M\Routine\CreateProcedure::class, $legacy);
        self::assertInstanceOf(M\Routine\CreateProcedure::class, $modern);
        self::assertInstanceOf(M\Routine\Program\Write\DeferredRevokePrivileges::class, $legacy->body);
        self::assertInstanceOf(M\Account\Privilege\RevokePrivileges::class, $modern->body);
    }

    public function testStatementRejectsAnImmediateRoutineWildcard(): void
    {
        $this->expectException(\SqlSemantics\Construction\RejectedInput::class);
        $this->expectExceptionMessage('The routine revocation does not name a routine object.');
        (new Semantics(Dialect::MySql))->analyze('REVOKE ALL ON FUNCTION * FROM u');
    }
}
