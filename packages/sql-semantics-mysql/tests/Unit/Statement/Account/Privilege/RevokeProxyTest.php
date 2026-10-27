<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Account\Privilege;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\RevokeProxy;

#[CoversClass(RevokeProxy::class)]
#[Medium]
final class RevokeProxyTest extends TestCase
{
    public function testDeriveStatementRecordsNoFact(): void
    {
        self::assertSame([], (new Semantics(Dialect::MySql))->analyze('REVOKE PROXY ON a FROM b')->diagnostics());
    }

    public function testRenderWritesEveryClause(): void
    {
        self::assertSame('REVOKE IF EXISTS PROXY ON a FROM b IGNORE UNKNOWN USER', (new Semantics(Dialect::MySql))->analyze('revoke if exists proxy on a from b ignore unknown user')->toString());
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

        new RevokeProxy($ifExists, new \SqlSemantics\Platform\MySql\Statement\Name\AccountName(new \SqlSemantics\Statement\Identifier\Name('proxy')), [$user], $ignoreUnknownUser);
    }

}
