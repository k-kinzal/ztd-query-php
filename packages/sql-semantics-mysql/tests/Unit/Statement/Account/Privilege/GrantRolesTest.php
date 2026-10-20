<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\Privilege;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantRoles;

#[CoversClass(GrantRoles::class)]
#[Medium]
final class GrantRolesTest extends TestCase
{
    public function testRenderRequiresAConsistentOrdinaryGrant(): void
    {
        $this->expectExceptionMessage('A role grant excludes anonymous operands and explicit self-grants.');
        new GrantRoles([new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\GrantedRole(new \SqlSemantics\Platform\MySql\Statement\Name\AccountName(new \SqlSemantics\Statement\Identifier\Name('r')))], [new \SqlSemantics\Platform\MySql\Statement\Name\AccountName(new \SqlSemantics\Statement\Identifier\Name('r'))]);
    }

    public function testDeriveStatementNeedsNoExpressionBindings(): void
    {
        self::assertSame([], (new Semantics(Dialect::MySql))->analyze('GRANT r TO u')->facts->diagnostics);
    }

    public function testRenderWritesTheAdminOption(): void
    {
        self::assertSame('GRANT r TO u WITH ADMIN OPTION', (new Semantics(Dialect::MySql))->analyze('grant r to u with admin option')->toString());
    }
}
