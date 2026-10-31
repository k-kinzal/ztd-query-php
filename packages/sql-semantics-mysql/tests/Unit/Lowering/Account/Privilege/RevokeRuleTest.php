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
        $operation = (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze($sql);
        self::assertInstanceOf(M\Statement::class, $operation);
        $legacy = $operation;
        $operation = (new Semantics(Dialect::MySql, 'mysql-8.4.7'))->analyze($sql);
        self::assertInstanceOf(M\Statement::class, $operation);
        $modern = $operation;
        self::assertInstanceOf(M\Routine\CreateProcedure::class, $legacy);
        self::assertInstanceOf(M\Routine\CreateProcedure::class, $modern);
        self::assertInstanceOf(M\Account\Privilege\RevokePrivileges::class, $legacy->body);
        self::assertInstanceOf(M\Account\Privilege\RevokePrivileges::class, $modern->body);
    }

    public function testStatementRetainsARoutineWildcard(): void
    {
        $stored = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() REVOKE ALL ON FUNCTION * FROM u');
        self::assertInstanceOf(M\Routine\CreateProcedure::class, $stored);
        $operation = $stored->body;
        self::assertInstanceOf(M\Account\Privilege\RevokePrivileges::class, $operation);
        self::assertTrue($operation->hasExecutionCondition(\SqlSemantics\Contract\GrammarRelease::MySql847));
    }
}
