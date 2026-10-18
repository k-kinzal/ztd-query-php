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

#[CoversClass(RevokePrivileges::class)]
#[Medium]
final class RevokePrivilegesTest extends TestCase
{
    public function testDeriveStatementAcceptsAnAbsentTable(): void
    {
        $revoke = (new Semantics(Dialect::MySql))->analyze('REVOKE SELECT ON t FROM u', []);

        self::assertInstanceOf(RevokePrivileges::class, $revoke->statement);
        self::assertInstanceOf(AbsentGrantTable::class, $revoke->facts->relation($revoke->statement->level)->table);
    }

    public function testRenderWritesEveryClause(): void
    {
        self::assertSame('REVOKE IF EXISTS ALL ON PROCEDURE p FROM u IGNORE UNKNOWN USER', (new Semantics(Dialect::MySql))->analyze('revoke if exists all privileges on procedure p from u ignore unknown user')->toString());
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
        $user = new \SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification(new \SqlSemantics\Platform\MySql\Statement\Name\AccountName(new \SqlSemantics\Statement\Identifier\Name('u')), new \SqlSemantics\Platform\MySql\Statement\Account\User\Identification(null, \SqlSemantics\Platform\MySql\Statement\Account\User\Credential::Password, new \SqlSemantics\Platform\MySql\Statement\Literal\Text('pw')));

        $this->expectExceptionMessage('A REVOKE account has no authentication clause.');

        new RevokePrivileges($ifExists, [new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\AllPrivileges()], \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectKind::Table, new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\GlobalLevel(), [$user], $ignoreUnknownUser);
    }

    public function testRoutineWildcardCannotBeConstructedAsAnOrdinaryStatement(): void
    {
        $this->expectExceptionMessage('An ordinary revocation names a routine object or a global ALL interpretation.');
        new RevokePrivileges(false, [new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\AllPrivileges()], \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectKind::Function, new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\CurrentDatabaseLevel(), [new \SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification(new \SqlSemantics\Platform\MySql\Statement\Name\CurrentUser())]);
    }
}
