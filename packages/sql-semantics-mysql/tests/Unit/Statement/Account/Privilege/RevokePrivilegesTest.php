<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\Privilege;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\AbsentGrantTable;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\RevokePrivileges;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(RevokePrivileges::class)]
#[Medium]
final class RevokePrivilegesTest extends TestCase
{
    public function testDeriveStatementAcceptsAnAbsentTable(): void
    {
        $revoke = (new Semantics(Dialect::MySql))->analyze('REVOKE SELECT ON t FROM u', []);
        self::assertInstanceOf(M\Statement::class, $revoke);

        self::assertInstanceOf(RevokePrivileges::class, $revoke);
        self::assertInstanceOf(\SqlSemantics\Statement\TableUse::class, $revoke->level);
        self::assertInstanceOf(AbsentGrantTable::class, $revoke->level->relationInformation()->table);
    }

    public function testRenderWritesEveryClause(): void
    {
        self::assertSame('REVOKE IF EXISTS ALL PRIVILEGES ON PROCEDURE p FROM u IGNORE UNKNOWN USER', (new Semantics(Dialect::MySql))->analyze('revoke if exists all privileges on procedure p from u ignore unknown user')->toString());
    }
    /**
     * @return iterable<string, array{bool, bool}>
     */
    public static function providerOptions(): iterable
    {
        yield 'no options' => [false, false];
        yield 'if exists' => [true, false];
        yield 'ignore unknown user' => [false, true];
        yield 'both' => [true, true];
    }

    #[DataProvider('providerOptions')]
    public function testRejectsAuthenticationClauses(bool $ifExists, bool $ignoreUnknownUser): void
    {
        $user = new M\Account\User\UserSpecification(new M\Name\AccountName(new Name('u')), new M\Account\User\Identification(null, M\Account\User\Credential::Password, new M\Literal\Text('pw')));

        $this->expectExceptionMessage('A REVOKE account has no authentication clause.');

        new RevokePrivileges($ifExists, [new M\Account\Privilege\Item\AllPrivileges()], M\Account\Privilege\Level\ObjectKind::Table, new M\Account\Privilege\Level\GlobalLevel(), [$user], $ignoreUnknownUser);
    }

    public function testHasExecutionConditionRetainsARoutineWildcard(): void
    {
        $request = new RevokePrivileges(false, [new M\Account\Privilege\Item\AllPrivileges()], M\Account\Privilege\Level\ObjectKind::Function, new M\Account\Privilege\Level\CurrentDatabaseLevel(), [new M\Account\User\UserSpecification(new M\Name\CurrentUser())]);
        self::assertTrue($request->hasExecutionCondition(\SqlSemantics\Contract\GrammarRelease::MySql847));
    }
    public function testDeriveStatementStoredRetainsEveryRecipientAndOption(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() REVOKE IF EXISTS ALL ON PROCEDURE * FROM u, CURRENT_USER IGNORE UNKNOWN USER');
        self::assertInstanceOf(M\Statement::class, $operation);
        self::assertInstanceOf(M\Routine\CreateProcedure::class, $operation);
        self::assertInstanceOf(RevokePrivileges::class, $operation->body);
        self::assertTrue($operation->body->ifExists);
        self::assertTrue($operation->body->ignoreUnknownUser);
        self::assertCount(2, $operation->body->users);
    }

    public function testHasExecutionConditionRetainsTheWildcardTarget(): void
    {
        $request = new RevokePrivileges(false, [new M\Account\Privilege\Item\AllPrivileges()], M\Account\Privilege\Level\ObjectKind::Procedure, new M\Account\Privilege\Level\CurrentDatabaseLevel(), [new M\Account\User\UserSpecification(new M\Name\AccountName(new Name('u')))]);
        self::assertTrue($request->hasExecutionCondition(\SqlSemantics\Contract\GrammarRelease::MySql847));
    }

    public function testRenderWritesTheStoredTargetCondition(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() REVOKE IF EXISTS ALL ON FUNCTION d.* FROM u IGNORE UNKNOWN USER');
        self::assertInstanceOf(M\Statement::class, $operation);
        self::assertSame('CREATE PROCEDURE p() REVOKE IF EXISTS ALL ON FUNCTION d.* FROM u IGNORE UNKNOWN USER', $operation->toString());
    }


    public function testHasExecutionConditionSelectsTheGlobalAllMeaningByRelease(): void
    {
        $request = new RevokePrivileges(false, [new M\Account\Privilege\Item\AllPrivileges()], M\Account\Privilege\Level\ObjectKind::Function, new M\Account\Privilege\Level\GlobalLevel(), [new M\Account\User\UserSpecification(new M\Name\CurrentUser())]);
        self::assertTrue($request->hasExecutionCondition(\SqlSemantics\Contract\GrammarRelease::MySql5651));
        self::assertTrue($request->hasExecutionCondition(\SqlSemantics\Contract\GrammarRelease::MySql5744));
        self::assertFalse($request->hasExecutionCondition(\SqlSemantics\Contract\GrammarRelease::MySql847));
    }

    public function testProblemAnswersADynamicPrivilegeOutsideTheGlobalLevelUnlessIfExists(): void
    {
        $database = new M\Account\Privilege\Level\DatabaseLevel(new Name('d'));
        $dynamic = new M\Account\Privilege\Item\DynamicPrivilege(new Name('BACKUP_ADMIN'));
        $user = new M\Account\User\UserSpecification(new M\Name\AccountName(new Name('u')));

        $release = \SqlSemantics\Contract\GrammarRelease::MySql847;
        $all = new RevokePrivileges(false, [new M\Account\Privilege\Item\AllPrivileges()], M\Account\Privilege\Level\ObjectKind::Function, new M\Account\Privilege\Level\GlobalLevel(), [$user]);

        self::assertSame('ER_ILLEGAL_PRIVILEGE_LEVEL', (new RevokePrivileges(false, [$dynamic], M\Account\Privilege\Level\ObjectKind::Table, $database, [$user]))->problem($release)?->error);
        self::assertNull((new RevokePrivileges(true, [$dynamic], M\Account\Privilege\Level\ObjectKind::Table, $database, [$user]))->problem($release));
        self::assertNull($all->problem($release));
        self::assertSame('ER_ILLEGAL_GRANT_FOR_TABLE', $all->problem(\SqlSemantics\Contract\GrammarRelease::MySql5744)?->error);
    }
}
