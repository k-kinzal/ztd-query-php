<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Account\RoleDefinitions;
use SqlSemantics\Platform\MySql\Statement\Name\AccountName;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(RoleDefinitions::class)]
#[Small]
final class RoleDefinitionsTest extends TestCase
{
    /**
     * @return iterable<string, array{list<AccountName>, bool}>
     */
    public static function providerAccounts(): iterable
    {
        yield 'repeated name' => [[new AccountName(new Name('r')), new AccountName(new Name('r'))], true];
        yield 'default host' => [[new AccountName(new Name('r')), new AccountName(new Name('r'), new Name('%'))], true];
        yield 'trimmed user and host case' => [[new AccountName(new Name(' r '), new Name('Host')), new AccountName(new Name('r'), new Name(' host '))], true];
        yield 'anonymous' => [[new AccountName(new Name(''))], true];
        yield 'distinct user case' => [[new AccountName(new Name('r')), new AccountName(new Name('R'))], false];
        yield 'distinct host' => [[new AccountName(new Name('r')), new AccountName(new Name('r'), new Name('localhost'))], false];
    }

    /**
     * @param list<AccountName> $roles The complete account operands
     */
    #[DataProvider('providerAccounts')]
    public function testProblemDistinguishesConditionalSkipsFromUnconditionalContradictions(array $roles, bool $contradictory): void
    {
        self::assertSame($contradictory, (new RoleDefinitions())->problem(false, $roles) !== null);
        self::assertNull((new RoleDefinitions())->problem(true, $roles));
    }
}
