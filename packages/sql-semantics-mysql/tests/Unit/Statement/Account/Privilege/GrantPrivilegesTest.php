<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\Privilege;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantPrivileges;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\UnknownGrantColumn;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;

#[CoversClass(GrantPrivileges::class)]
#[Medium]
final class GrantPrivilegesTest extends TestCase
{
    public function testDeriveStatementResolvesTheTableAndItsColumns(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $table = $semantics->analyze('CREATE TABLE t (a INT)');
        $grant = $semantics->analyze('GRANT SELECT (a, b) ON t TO u', [$table]);

        self::assertInstanceOf(GrantPrivileges::class, $grant->statement);
        self::assertInstanceOf(DeclaredTable::class, $grant->facts->relation($grant->statement->level)->table);
        self::assertInstanceOf(UnknownGrantColumn::class, $grant->facts->diagnostics[0]);
    }

    public function testDeriveStatementRefusesAsOutsideTheGlobalLevel(): void
    {
        $this->expectException(\SqlSemantics\Construction\RejectedInput::class);
        $this->expectExceptionMessage('AS clause cannot be granted at database db (ER_UNSUPPORTED_USE_OF_GRANT_AS).');

        (new Semantics(Dialect::MySql))->analyze('GRANT SELECT ON db.* TO root AS CURRENT_USER');
    }

    public function testRenderWritesEveryClause(): void
    {
        self::assertSame("GRANT EXECUTE ON FUNCTION db.f TO u IDENTIFIED BY 'x' REQUIRE NONE WITH MAX_USER_CONNECTIONS 2", (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze("grant execute on function db.f to u identified by 'x' require none with max_user_connections 2")->toString());
    }
    /**
     * @return iterable<string, array{M\Account\Privilege\Item\PrivilegeKind}>
     */
    public static function providerRolePrivileges(): iterable
    {
        yield 'CREATE ROLE' => [M\Account\Privilege\Item\PrivilegeKind::CreateRole];
        yield 'DROP ROLE' => [M\Account\Privilege\Item\PrivilegeKind::DropRole];
    }

    #[DataProvider('providerRolePrivileges')]
    public function testRenderExcludesCombiningRolePrivilegesWithLegacyResourceLimits(M\Account\Privilege\Item\PrivilegeKind $privilege): void
    {
        $this->expectExceptionMessage('cannot combine features removed before MySQL 8.0');
        new GrantPrivileges(
            [new M\Account\Privilege\Item\StaticPrivilege($privilege)],
            M\Account\Privilege\Level\ObjectKind::Table,
            new M\Account\Privilege\Level\GlobalLevel(),
            [new M\Account\User\Grantee(new M\Name\AccountName(new Name('u')))],
            options: [new M\Account\Option\ResourceLimit(M\Account\Option\ResourceKind::QueriesPerHour, new M\Literal\Numeral('1'))],
        );
    }

    #[DataProvider('providerRolePrivileges')]
    public function testRenderRetainsModernRolePrivilegesWithGrantOption(M\Account\Privilege\Item\PrivilegeKind $privilege): void
    {
        $grant = new GrantPrivileges(
            [new M\Account\Privilege\Item\StaticPrivilege($privilege)],
            M\Account\Privilege\Level\ObjectKind::Table,
            new M\Account\Privilege\Level\GlobalLevel(),
            [new M\Account\User\Grantee(new M\Name\AccountName(new Name('u')))],
            options: [new M\Account\Privilege\GrantOptionRight()],
        );
        self::assertInstanceOf(M\Account\Privilege\Item\StaticPrivilege::class, $grant->privileges[0]);
        self::assertSame($privilege, $grant->privileges[0]->kind);
        self::assertTrue((new \SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms())->requires($privilege));
    }
}
