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

        self::assertInstanceOf(MisplacedPrivilege::class, $semantics->analyze('REVOKE BACKUP_ADMIN ON db.* FROM u')->diagnostics()[0]);
        self::assertSame([], $semantics->analyze('REVOKE IF EXISTS BACKUP_ADMIN ON db.* FROM u')->diagnostics());
    }

    public function testFitReportsAPrivilegeOutsideItsLevel(): void
    {
        $semantics = new Semantics(Dialect::MySql);

        self::assertInstanceOf(MisplacedPrivilege::class, $semantics->analyze('REVOKE EXECUTE ON t FROM u')->diagnostics()[0]);
        self::assertInstanceOf(MisplacedPrivilege::class, $semantics->analyze('REVOKE SELECT ON PROCEDURE p FROM u')->diagnostics()[0]);
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
        $statement = (new Semantics(Dialect::MySql, $release))->analyze('REVOKE ALL ON FUNCTION *.* FROM u');
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Account\Privilege\RevokePrivileges::class, $statement);
        self::assertTrue($statement->hasExecutionCondition($statement->profile()->grammar));
        self::assertInstanceOf(MisplacedPrivilege::class, $statement->diagnostics()[0]);
        self::assertSame('REVOKE ALL ON FUNCTION *.* FROM u', $statement->toString());
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerLegacyGlobalAll')]
    #[\PHPUnit\Framework\Attributes\DataProvider('providerModernGlobalAll')]
    public function testGrantGlobalAllRequiresTheRoutineObjectInEveryRelease(string $release): void
    {
        $statement = (new Semantics(Dialect::MySql, $release))->analyze('GRANT ALL ON FUNCTION *.* TO u');
        self::assertInstanceOf(GrantPrivileges::class, $statement);
        self::assertSame('ER_ILLEGAL_GRANT_FOR_TABLE', $statement->condition()?->error);
        self::assertEquals([$statement->condition()], $statement->diagnostics());
        self::assertSame('GRANT ALL ON FUNCTION *.* TO u', $statement->toString());
    }
}
