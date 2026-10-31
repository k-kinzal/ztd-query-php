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
use SqlSemantics\Platform\MySql\Statement\Name\AccountName;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Reference\Table\MissingTable;

#[CoversClass(PrivilegeChecks::class)]
#[Medium]
final class PrivilegeChecksTest extends TestCase
{
    public function testColumnLevelDistinguishesWildcardAndIndividualTargets(): void
    {
        $privilege = new StaticPrivilege(PrivilegeKind::Select, [new Name('a')]);
        $level = new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\GlobalLevel();
        $problem = (new PrivilegeChecks())->columnLevel([$privilege], $level);
        self::assertNotNull($problem);
        self::assertSame($privilege, $problem->privilege);
        self::assertSame($level, $problem->level);
        self::assertNull((new PrivilegeChecks())->columnLevel([$privilege], new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectLevel(new \SqlSemantics\Statement\Identifier\QualifiedName(new Name('t')))));
        self::assertNull((new PrivilegeChecks())->columnLevel([new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\AllPrivileges()], $level));
    }

    public function testNoticesPublishesEveryDirectlyConstructedSuperOccurrence(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $grant = new GrantPrivileges([
            new StaticPrivilege(PrivilegeKind::Super),
            new StaticPrivilege(PrivilegeKind::Super),
        ], \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectKind::Procedure, new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\GlobalLevel(), [new \SqlSemantics\Platform\MySql\Statement\Account\User\Grantee(new \SqlSemantics\Platform\MySql\Statement\Name\CurrentUser())]);
        $statement = Semantics::fromStatement($semantics->context(), $grant);
        self::assertSame(['The SUPER privilege identifier is deprecated', 'The SUPER privilege identifier is deprecated'], array_map(static fn ($warning): string => $warning->message(), $statement->warnings()));
    }

    public function testGrantRequiresTheTableWithoutCreate(): void
    {
        self::assertInstanceOf(MissingTable::class, (new Semantics(Dialect::MySql))->analyze('GRANT SELECT ON t TO u', [])->diagnostics()[0]);
    }

    public function testRevokeAcceptsAnAbsentTable(): void
    {
        self::assertSame([], (new Semantics(Dialect::MySql))->analyze('REVOKE SELECT ON t FROM u', [])->diagnostics());
    }

    public function testLevelReportsADynamicPrivilegeOutsideTheGlobalLevel(): void
    {
        $semantics = new Semantics(Dialect::MySql);

        $stored = $semantics->analyze('CREATE PROCEDURE p() REVOKE BACKUP_ADMIN ON db.* FROM u');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $stored);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Account\Privilege\RevokePrivileges::class, $stored->body);
        self::assertInstanceOf(MisplacedPrivilege::class, $stored->body->problem(\SqlSemantics\Contract\GrammarRelease::MySql847));
        self::assertSame([], $semantics->analyze('REVOKE IF EXISTS BACKUP_ADMIN ON db.* FROM u')->diagnostics());
    }

    public function testFitReportsAPrivilegeOutsideItsLevel(): void
    {
        $semantics = new Semantics(Dialect::MySql);

        $table = $semantics->analyze('CREATE PROCEDURE p() REVOKE EXECUTE ON t FROM u');
        $routine = $semantics->analyze('CREATE PROCEDURE q() REVOKE SELECT ON PROCEDURE p FROM u');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $table);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $routine);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Account\Privilege\RevokePrivileges::class, $table->body);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Account\Privilege\RevokePrivileges::class, $routine->body);
        self::assertInstanceOf(MisplacedPrivilege::class, $table->body->problem(\SqlSemantics\Contract\GrammarRelease::MySql847));
        self::assertInstanceOf(MisplacedPrivilege::class, $routine->body->problem(\SqlSemantics\Contract\GrammarRelease::MySql847));
    }

    public function testColumnPrivilegesOnRoutinesAreRefusedBeforeDerivation(): void
    {
        $this->expectException(\SqlSemantics\Construction\RejectedInput::class);
        $this->expectExceptionMessage('Column privileges apply only to tables.');

        (new Semantics(Dialect::MySql))->analyze('GRANT SELECT (a) ON PROCEDURE p TO u');
    }


    public function testTargetResolvesTheTable(): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $grant = $semantics->analyze('GRANT SELECT ON t TO u', [$semantics->analyze('CREATE TABLE t (a INT, b INT)')]);

        self::assertInstanceOf(GrantPrivileges::class, $grant);
        self::assertInstanceOf(\SqlSemantics\Statement\TableUse::class, $grant->level);
        self::assertCount(2, $grant->level->relationInformation()->shape->slots);
    }

    public function testDescribeNamesTheItem(): void
    {
        self::assertSame(['GRANT OPTION', 'r@h'], [(new PrivilegeChecks())->describe(new StaticPrivilege(PrivilegeKind::GrantOption)), (new PrivilegeChecks())->describe(new GrantedRole(new AccountName(new Name('r'), new Name('h'))))]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerModernGlobalAll(): iterable
    {
        yield '8.0' => ['mysql-8.0.44'];
        yield '8.4' => ['mysql-8.4.7'];
        yield '9.1' => ['mysql-9.1.0'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerLegacyGlobalAll(): iterable
    {
        yield '5.6' => ['mysql-5.6.51'];
        yield '5.7' => ['mysql-5.7.44'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerModernGlobalAll')]
    public function testRevokeGlobalAllHasNoRoutineTargetInModernReleases(string $release): void
    {
        $semantics = new Semantics(Dialect::MySql, $release);
        self::assertSame([], $semantics->analyze('REVOKE ALL ON FUNCTION *.* FROM u')->diagnostics());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerLegacyGlobalAll')]
    public function testRevokeRetainsTheLegacyGlobalRoutineCondition(string $release): void
    {
        try {
            (new Semantics(Dialect::MySql, $release))->analyze('REVOKE ALL ON FUNCTION *.* FROM u');
            self::fail('A legacy release refuses a routine kind at the global level.');
        } catch (\SqlSemantics\Construction\RejectedInput $failure) {
            self::assertInstanceOf(MisplacedPrivilege::class, $failure->problem);
        }
        $stored = (new Semantics(Dialect::MySql, $release))->analyze('CREATE PROCEDURE p() REVOKE ALL ON FUNCTION *.* FROM u');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $stored);
        $statement = $stored->body;
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Account\Privilege\RevokePrivileges::class, $statement);
        self::assertTrue($statement->hasExecutionCondition(\SqlSemantics\Contract\GrammarRelease::from($release)));
        self::assertSame('REVOKE ALL ON FUNCTION *.* FROM u', $statement->toString());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerLegacyGlobalAll')]
    #[\PHPUnit\Framework\Attributes\DataProvider('providerModernGlobalAll')]
    public function testGrantGlobalAllRequiresTheRoutineObjectInEveryRelease(string $release): void
    {
        try {
            (new Semantics(Dialect::MySql, $release))->analyze('GRANT ALL ON FUNCTION *.* TO u');
            self::fail('A routine kind does not fit the global level.');
        } catch (\SqlSemantics\Construction\RejectedInput $failure) {
            self::assertInstanceOf(MisplacedPrivilege::class, $failure->problem);
            self::assertSame('ER_ILLEGAL_GRANT_FOR_TABLE', $failure->problem->error);
        }
        $stored = (new Semantics(Dialect::MySql, $release))->analyze('CREATE PROCEDURE p() GRANT ALL ON FUNCTION *.* TO u');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure::class, $stored);
        $statement = $stored->body;
        self::assertInstanceOf(GrantPrivileges::class, $statement);
        self::assertSame('ER_ILLEGAL_GRANT_FOR_TABLE', $statement->condition()?->error);
        self::assertSame('GRANT ALL ON FUNCTION *.* TO u', $statement->toString());
    }

    public function testProblemAnswersTheFirstPrivilegeOutsideTheLevel(): void
    {
        $checks = new PrivilegeChecks();
        $database = new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\DatabaseLevel(new Name('d'));
        $reload = new StaticPrivilege(PrivilegeKind::Reload);
        $select = new StaticPrivilege(PrivilegeKind::Select);
        $dynamic = new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\DynamicPrivilege(new Name('BACKUP_ADMIN'));

        self::assertNull($checks->problem([$select], \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectKind::Table, $database, false));
        self::assertNull($checks->problem([$select, $reload], \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectKind::Table, $database, false));
        self::assertSame('ER_WRONG_USAGE', $checks->usage([$select, $reload], \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectKind::Table, $database)?->error);
        self::assertNull($checks->usage([$select], \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectKind::Table, $database));
        self::assertSame('ER_ILLEGAL_PRIVILEGE_LEVEL', $checks->problem([$dynamic], \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectKind::Table, $database, false)?->error);
        self::assertNull($checks->problem([$dynamic], \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectKind::Table, $database, true));
        self::assertSame('ER_ILLEGAL_GRANT_FOR_TABLE', $checks->problem([$select], \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectKind::Procedure, $database, false)?->error);
    }

    public function testUsageAnswersAGlobalPrivilegeAtTheDatabaseLevel(): void
    {
        $checks = new PrivilegeChecks();
        $database = new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\DatabaseLevel(new Name('d'));

        self::assertSame('RELOAD', $checks->usage([new StaticPrivilege(PrivilegeKind::Reload)], \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectKind::Table, $database)?->privilege);
        self::assertNull($checks->usage([new StaticPrivilege(PrivilegeKind::Reload)], \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectKind::Table, new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\GlobalLevel()));
    }

    public function testMisfitAnswersWhyAStaticPrivilegeDoesNotFit(): void
    {
        $checks = new PrivilegeChecks();
        $table = new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectLevel(new \SqlSemantics\Statement\Identifier\QualifiedName(new Name('t')));
        $global = new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\GlobalLevel();
        $columns = new StaticPrivilege(PrivilegeKind::Select, [new Name('a')]);

        self::assertNull($checks->misfit(new StaticPrivilege(PrivilegeKind::Reload), \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectKind::Table, $global));
        self::assertSame('ER_ILLEGAL_GRANT_FOR_TABLE', $checks->misfit($columns, \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectKind::Table, $global)?->error);
        self::assertSame('ER_PARSE_ERROR', $checks->misfit($columns, \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectKind::Procedure, $table)?->error);
        self::assertSame('ER_ILLEGAL_GRANT_FOR_TABLE', $checks->misfit(new StaticPrivilege(PrivilegeKind::Execute), \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectKind::Table, $table)?->error);
        self::assertNull($checks->misfit(new StaticPrivilege(PrivilegeKind::Execute), \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectKind::Procedure, $table));
    }
}
