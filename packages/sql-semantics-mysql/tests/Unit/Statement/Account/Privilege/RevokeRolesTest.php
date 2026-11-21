<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\Privilege;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Semantics;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\RevokeRoles;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\RoleOrPrivilegeMismatch;

#[CoversClass(RevokeRoles::class)]
#[Medium]
final class RevokeRolesTest extends TestCase
{
    public function testDeriveStatementReportsAPrivilegeInTheRoleList(): void
    {
        self::assertInstanceOf(RoleOrPrivilegeMismatch::class, (new Semantics())->inspect('REVOKE BACKUP_ADMIN (c) FROM u')->facts->diagnostics[0]);
    }

    public function testRenderWritesEveryClause(): void
    {
        self::assertSame('REVOKE IF EXISTS r1, r2 FROM u IGNORE UNKNOWN USER', (new Semantics())->inspect('revoke if exists r1, r2 from u ignore unknown user')->toString());
    }
}
