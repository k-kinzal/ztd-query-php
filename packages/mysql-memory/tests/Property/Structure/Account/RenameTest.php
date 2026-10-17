<?php

declare(strict_types=1);

namespace Tests\Property\Structure\Account;

use Fuzz\Structure\Choices;
use Fuzz\Structure\Execution;
use MySqlMemory\Account\Identity;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Account\CreateUser;
use SqlSemantics\Platform\MySql\Statement\Account\RenameUser;
use SqlSemantics\Platform\MySql\Statement\Account\User\UserRenaming;
use SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification;
use SqlSemantics\Platform\MySql\Statement\Call\KeywordCall;
use SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction;
use SqlSemantics\Platform\MySql\Statement\Name\AccountName;
use SqlSemantics\Platform\MySql\Statement\Name\CurrentUser;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Typed account occurrences update connection identity separately from account rollback.
 */
#[CoversNothing]
#[Medium]
final class RenameTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int}>
     */
    public static function providerHistories(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            for ($seed = 0; $seed < 20; $seed++) {
                yield $release . ':' . $seed => [$release, $seed];
            }
        }
    }

    /**
     * Generates rename chains and a possible final failure without submitting SQL.
     *
     * @throws SqlError When the fixture or subsequent identity read fails
     */
    #[DataProvider('providerHistories')]
    public function testCurrentUserFollowsItsOccurrencesAndRetainsConnectionState(string $release, int $seed): void
    {
        $choices = new Choices('rename-current:' . $seed);
        $user = ($seed % 2 === 0 ? 'user_' : 'u@') . $seed;
        $host = $choices->one(['%', 'h', '127.0.0.1']);
        $targets = array_map(static fn (int $index): Identity => new Identity('renamed_' . $seed . '_' . $index, $host), range(0, $choices->integer(1, 5)));
        $pairs = array_map(static fn (Identity $target): UserRenaming => new UserRenaming(new CurrentUser(), new AccountName(new Name($target->user), new Name($target->host))), $targets);
        $fails = $seed % 3 === 0;
        if ($fails) {
            $pairs[] = new UserRenaming(new AccountName(new Name('missing')), new AccountName(new Name('absent')));
        }
        $instance = new Instance($release);
        $execution = new Execution();
        $execution->runIn($instance->connect(), new CreateUser(false, [new UserSpecification(new AccountName(new Name($user)))]));
        $session = $instance->connect($user);
        $other = $instance->connect($user);
        $session->text = 'unrelated invalid SQL';
        $error = null;
        try {
            $execution->runIn($session, new RenameUser($pairs));
        } catch (SqlError $caught) {
            $error = $caught;
        }
        $legacy = $session->settings()->legacy();
        $last = $targets[count($targets) - 1];
        self::assertSame($legacy || $fails ? 1396 : null, $error?->getCode());
        self::assertSame($legacy ? $user . '@%' : $last->text(), $session->variables->definer->text());
        self::assertSame(!$legacy && $fails, $instance->accounts->find(new Identity($user, '%')) !== null);
        self::assertSame(!$legacy && !$fails, $instance->accounts->find($last) !== null);
        self::assertSame($legacy, $instance->accounts->find($targets[0]) !== null);
        $read = $execution->runIn($session, new KeywordCall(KeywordFunction::CurrentUser, []));
        $watched = $execution->runIn($other, new KeywordCall(KeywordFunction::CurrentUser, []));
        $login = $execution->runIn($session, new KeywordCall(KeywordFunction::User, []));
        self::assertInstanceOf(ResultSet::class, $read);
        self::assertInstanceOf(ResultSet::class, $watched);
        self::assertInstanceOf(ResultSet::class, $login);
        self::assertSame([[$legacy ? $user . '@%' : $last->text()]], $read->rows);
        self::assertSame([[$user . '@%']], $watched->rows);
        self::assertSame([[$user . '@localhost']], $login->rows);
    }
}
