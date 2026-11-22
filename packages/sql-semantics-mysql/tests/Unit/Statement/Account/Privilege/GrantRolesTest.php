<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\Privilege;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantRoles;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\RoleOrPrivilegeMismatch;
use SqlSemantics\Testing\MySql\Inspection;

#[CoversClass(GrantRoles::class)]
#[Medium]
final class GrantRolesTest extends TestCase
{
    public function testDeriveStatementReportsAPrivilegeInTheRoleList(): void
    {
        self::assertInstanceOf(RoleOrPrivilegeMismatch::class, (new Inspection())->diagnose('GRANT r, UPDATE TO u')->facts->diagnostics[0]);
    }

    public function testRenderWritesTheAdminOption(): void
    {
        self::assertSame('GRANT r TO u WITH ADMIN OPTION', (new Inspection())->diagnose('grant r to u with admin option')->toString());
    }
}
