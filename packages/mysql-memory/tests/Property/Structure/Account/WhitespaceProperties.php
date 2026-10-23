<?php

declare(strict_types=1);

namespace Tests\Property\Structure\Account;

use MySqlMemory\Account\Identity;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Account\CreateUser;
use SqlSemantics\Platform\MySql\Statement\Account\DropUser;
use SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification;
use SqlSemantics\Platform\MySql\Statement\Name\AccountName;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowGrants;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Fake\MemoryClient as Execution;
use Tests\Generator\Choices;

/**
 * Independent semantic operands resolve the same account after whitespace normalization.
 */
#[CoversNothing]
#[Medium]
final class WhitespaceProperties extends TestCase
{
    /**
     * @return iterable<string, array{string, int}>
     */
    public static function providerHistories(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            for ($seed = 0; $seed < 40; $seed++) {
                yield $release . ':' . $seed => [$release, $seed];
            }
        }
    }

    /**
     * Generates padded account operands and uses separately constructed names without SQL.
     */
    #[DataProvider('providerHistories')]
    #[\PHPUnit\Framework\Attributes\Test]
    public function satisfiesNormalizedNamesReachTheCreatedAccount(string $release, int $seed): void
    {
        $choices = new Choices('account-whitespace:' . $seed);
        $spaces = [' ', "\t", "\n", "\r", "\v", "\f"];
        $padding = $release === '5.6.51' ? 6 : 40;
        $prefix = str_repeat($choices->one($spaces), $choices->integer(1, $padding));
        $suffix = str_repeat($choices->one($spaces), $choices->integer(1, $padding));
        $user = 'u' . $seed;
        $host = $choices->one(['%', 'HOST', '127.0.0.1']);
        $session = (new Instance($release))->connect();
        $session->text = 'unrelated invalid SQL';
        $execution = new Execution();
        $execution->runIn($session, new CreateUser(false, [new UserSpecification(new AccountName(new Name($prefix . $user . $suffix), new Name($suffix . $host . $prefix)))]));
        $result = $execution->runIn($session, new ShowGrants(new AccountName(new Name($user), new Name(strtolower($host)))));

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertCount(1, $result->rows);
        self::assertNotNull($session->instance->accounts->find(Identity::of($user, $host)));

        $execution->runIn($session, new DropUser(false, [new AccountName(new Name($suffix . $user . $prefix), new Name($prefix . $host . $suffix))]));

        self::assertNull($session->instance->accounts->find(Identity::of($user, $host)));
    }
}
