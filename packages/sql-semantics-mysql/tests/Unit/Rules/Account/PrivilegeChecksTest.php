<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Rules\Account\PrivilegeChecks;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantPrivileges;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\GrantedRole;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\PrivilegeKind;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\StaticPrivilege;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\MisplacedPrivilege;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\RoleOrPrivilegeMismatch;
use SqlSemantics\Platform\MySql\Statement\Name\AccountName;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Reference\Table\MissingTable;

#[CoversClass(PrivilegeChecks::class)]
#[Medium]
final class PrivilegeChecksTest extends TestCase
{
    public function testGrantRequiresTheTableWithoutCreate(): void
    {
        self::assertInstanceOf(MissingTable::class, (new Semantics(Dialect::MySql))->analyze('GRANT SELECT ON t TO u', [])->facts->diagnostics[0]);
    }

    public function testRevokeAcceptsAnAbsentTable(): void
    {
        self::assertSame([], (new Semantics(Dialect::MySql))->analyze('REVOKE SELECT ON t FROM u', [])->facts->diagnostics);
    }

    public function testLevelReportsADynamicPrivilegeOutsideTheGlobalLevel(): void
    {
        $semantics = new Semantics(Dialect::MySql);

        self::assertInstanceOf(MisplacedPrivilege::class, $semantics->analyze('GRANT BACKUP_ADMIN ON db.* TO u')->facts->diagnostics[0]);
        self::assertSame([], $semantics->analyze('REVOKE IF EXISTS BACKUP_ADMIN ON db.* FROM u')->facts->diagnostics);
    }

    public function testFitReportsAPrivilegeOutsideItsLevel(): void
    {
        $semantics = new Semantics(Dialect::MySql);

        self::assertInstanceOf(MisplacedPrivilege::class, $semantics->analyze('GRANT EXECUTE ON t TO u')->facts->diagnostics[0]);
        self::assertInstanceOf(MisplacedPrivilege::class, $semantics->analyze('GRANT SELECT ON PROCEDURE p TO u')->facts->diagnostics[0]);
        self::assertInstanceOf(MisplacedPrivilege::class, $semantics->analyze('GRANT SELECT (a) ON PROCEDURE p TO u')->facts->diagnostics[0]);
    }

    public function testRolesReportsAPrivilegeInARoleList(): void
    {
        self::assertInstanceOf(RoleOrPrivilegeMismatch::class, (new Semantics(Dialect::MySql))->analyze('REVOKE CREATE ROLE FROM u')->facts->diagnostics[0]);
    }

    public function testTargetResolvesTheTable(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $grant = $semantics->analyze('GRANT SELECT ON t TO u', [$semantics->analyze('CREATE TABLE t (a INT, b INT)')]);

        self::assertInstanceOf(GrantPrivileges::class, $grant->statement);
        self::assertCount(2, $grant->facts->relation($grant->statement->level)->shape->slots);
    }

    public function testDescribeNamesTheItem(): void
    {
        self::assertSame(['GRANT OPTION', 'r@h'], [(new PrivilegeChecks())->describe(new StaticPrivilege(PrivilegeKind::GrantOption)), (new PrivilegeChecks())->describe(new GrantedRole(new AccountName(new Name('r'), new Name('h'))))]);
    }
}
