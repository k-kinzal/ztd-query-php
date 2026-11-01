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

        self::assertInstanceOf(GrantPrivileges::class, $grant);
        self::assertInstanceOf(\SqlSemantics\Statement\TableUse::class, $grant->level);
        self::assertInstanceOf(DeclaredTable::class, $grant->level->relationInformation()->table);
        self::assertInstanceOf(UnknownGrantColumn::class, $grant->diagnostics()[0]);
    }

    public function testDeriveStatementKeepsAsOutsideTheGlobalLevel(): void
    {
        $stored = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() GRANT SELECT ON db.* TO root AS CURRENT_USER');
        self::assertInstanceOf(M\Routine\CreateProcedure::class, $stored);
        $statement = $stored->body;
        self::assertInstanceOf(GrantPrivileges::class, $statement);
        self::assertSame('AS clause cannot be granted at database db (ER_UNSUPPORTED_USE_OF_GRANT_AS).', $statement->condition()?->message());
        self::assertSame([], $stored->diagnostics());
        self::assertSame('GRANT SELECT ON db.* TO root AS CURRENT_USER', $statement->toString());
    }

    public function testConditionPrioritizesAsBeforeThePrivilegeScope(): void
    {
        $stored = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE q() GRANT SELECT ON PROCEDURE d.p TO u AS CURRENT_USER WITH ROLE ALL');
        self::assertInstanceOf(M\Routine\CreateProcedure::class, $stored);
        $statement = $stored->body;
        self::assertInstanceOf(GrantPrivileges::class, $statement);
        self::assertSame('ER_UNSUPPORTED_USE_OF_GRANT_AS', $statement->condition()?->error);
        self::assertSame('ER_ILLEGAL_GRANT_FOR_TABLE', $statement->scopeProblem()?->error);
        self::assertSame('GRANT SELECT ON PROCEDURE d.p TO u AS CURRENT_USER WITH ROLE ALL', $statement->toString());
    }

    public function testScopeProblemRetainsTheContradictoryTarget(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() GRANT SELECT ON PROCEDURE d.p TO u');
        self::assertInstanceOf(M\Routine\CreateProcedure::class, $create);
        self::assertInstanceOf(GrantPrivileges::class, $create->body);
        self::assertSame('SELECT cannot be granted at object d.p (ER_ILLEGAL_GRANT_FOR_TABLE).', $create->body->scopeProblem()?->message());
        self::assertSame([], $create->diagnostics());
    }

    public function testColumnLevelDistinguishesNonObjectTargets(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() GRANT SELECT(a) ON d.* TO u');
        self::assertInstanceOf(M\Routine\CreateProcedure::class, $create);
        self::assertInstanceOf(GrantPrivileges::class, $create->body);
        self::assertTrue($create->body->columnLevel());
        self::assertSame('CREATE PROCEDURE p() GRANT SELECT (a) ON d.* TO u', $create->toString());
    }

    public function testDeriveProgramKeepsInvocationConditionsBesideOrdinaryOperands(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() GRANT ALL ON PROCEDURE *.* TO u');
        self::assertInstanceOf(M\Routine\CreateProcedure::class, $create);
        self::assertInstanceOf(GrantPrivileges::class, $create->body);
        self::assertSame([], $create->diagnostics());
        self::assertSame('ER_ILLEGAL_GRANT_FOR_TABLE', $create->body->condition()?->error);
        self::assertSame('CREATE PROCEDURE p() GRANT ALL ON PROCEDURE *.* TO u', $create->toString());
    }

    public function testDeriveProgramRetainsAsAndRoleOperands(): void
    {
        $create = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() GRANT ALL ON d.* TO u AS CURRENT_USER WITH ROLE ALL');
        self::assertInstanceOf(M\Routine\CreateProcedure::class, $create);
        self::assertInstanceOf(GrantPrivileges::class, $create->body);
        self::assertSame([], $create->diagnostics());
        self::assertSame('ER_UNSUPPORTED_USE_OF_GRANT_AS', $create->body->condition()?->error);
        self::assertSame('CREATE PROCEDURE p() GRANT ALL ON d.* TO u AS CURRENT_USER WITH ROLE ALL', $create->toString());
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

    public function testProblemAnswersTheAsClauseBeforeTheLevel(): void
    {
        $database = new M\Account\Privilege\Level\DatabaseLevel(new Name('d'));
        $reload = new M\Account\Privilege\Item\StaticPrivilege(M\Account\Privilege\Item\PrivilegeKind::Reload);
        $grantee = new M\Account\User\Grantee(new M\Name\AccountName(new Name('u')));
        $as = new M\Account\Privilege\GrantAs(new M\Name\AccountName(new Name('u')));

        self::assertSame('ER_UNSUPPORTED_USE_OF_GRANT_AS', (new GrantPrivileges([$reload], M\Account\Privilege\Level\ObjectKind::Table, $database, [$grantee], as: $as))->problem()?->error);
        self::assertNull((new GrantPrivileges([$reload], M\Account\Privilege\Level\ObjectKind::Table, $database, [$grantee]))->problem());
        self::assertSame('ER_ILLEGAL_GRANT_FOR_TABLE', (new GrantPrivileges([new M\Account\Privilege\Item\StaticPrivilege(M\Account\Privilege\Item\PrivilegeKind::Execute)], M\Account\Privilege\Level\ObjectKind::Table, new M\Account\Privilege\Level\ObjectLevel(new \SqlSemantics\Statement\Identifier\QualifiedName(new Name('t'))), [$grantee]))->problem()?->error);
        self::assertNull((new GrantPrivileges([new M\Account\Privilege\Item\StaticPrivilege(M\Account\Privilege\Item\PrivilegeKind::Select)], M\Account\Privilege\Level\ObjectKind::Table, $database, [$grantee]))->problem());
    }
}
