<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Table\MissingTable;
use SqlSemantics\Platform\MySql\Rules\Account\PrivilegeChecks;
use SqlSemantics\Platform\MySql\Semantics;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantPrivileges;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\GrantedRole;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\PrivilegeKind;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\StaticPrivilege;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\MisplacedPrivilege;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\RoleOrPrivilegeMismatch;
use SqlSemantics\Platform\MySql\Statement\Name\AccountName;

#[CoversClass(PrivilegeChecks::class)]
#[Medium]
final class PrivilegeChecksTest extends TestCase
{
    public function testGrantRequiresTheTableWithoutCreate(): void
    {
        self::assertInstanceOf(MissingTable::class, (new Semantics())->inspect('GRANT SELECT ON t TO u', [])->facts->diagnostics[0]);
    }

    public function testRevokeAcceptsAnAbsentTable(): void
    {
        self::assertSame([], (new Semantics())->inspect('REVOKE SELECT ON t FROM u', [])->facts->diagnostics);
    }

    public function testLevelReportsADynamicPrivilegeOutsideTheGlobalLevel(): void
    {
        $semantics = new Semantics();

        self::assertInstanceOf(MisplacedPrivilege::class, $semantics->inspect('GRANT BACKUP_ADMIN ON db.* TO u')->facts->diagnostics[0]);
        self::assertSame([], $semantics->inspect('REVOKE IF EXISTS BACKUP_ADMIN ON db.* FROM u')->facts->diagnostics);
    }

    public function testFitReportsAPrivilegeOutsideItsLevel(): void
    {
        $semantics = new Semantics();

        self::assertInstanceOf(MisplacedPrivilege::class, $semantics->inspect('GRANT EXECUTE ON t TO u')->facts->diagnostics[0]);
        self::assertInstanceOf(MisplacedPrivilege::class, $semantics->inspect('GRANT SELECT ON PROCEDURE p TO u')->facts->diagnostics[0]);
        self::assertInstanceOf(MisplacedPrivilege::class, $semantics->inspect('GRANT SELECT (a) ON PROCEDURE p TO u')->facts->diagnostics[0]);
    }

    public function testRolesReportsAPrivilegeInARoleList(): void
    {
        self::assertInstanceOf(RoleOrPrivilegeMismatch::class, (new Semantics())->inspect('REVOKE CREATE ROLE FROM u')->facts->diagnostics[0]);
    }

    public function testTargetResolvesTheTable(): void
    {
        $semantics = new Semantics();
        $grant = $semantics->inspect('GRANT SELECT ON t TO u', [$semantics->inspect('CREATE TABLE t (a INT, b INT)')]);

        self::assertInstanceOf(GrantPrivileges::class, $grant->statement);
        self::assertCount(2, $grant->facts->relation($grant->statement->level)->shape->slots);
    }

    public function testDescribeNamesTheItem(): void
    {
        self::assertSame(['GRANT OPTION', 'r@h'], [(new PrivilegeChecks())->describe(new StaticPrivilege(PrivilegeKind::GrantOption)), (new PrivilegeChecks())->describe(new GrantedRole(new AccountName(new Name('r'), new Name('h'))))]);
    }
}
