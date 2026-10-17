<?php

declare(strict_types=1);

namespace Tests\Property\Structure;

use Fuzz\Structure\Choices;
use Fuzz\Structure\Execution;
use MySqlMemory\Account\Identity;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Account\CreateUser;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantPrivileges;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\PrivilegeKind;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\StaticPrivilege;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\DatabaseLevel;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectKind;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\RevokePrivileges;
use SqlSemantics\Platform\MySql\Statement\Account\User\Grantee;
use SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification;
use SqlSemantics\Platform\MySql\Statement\Name\AccountName;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Direct account lists retain repeated occurrences and their effect on transaction state.
 */
#[CoversNothing]
#[Medium]
final class RevokeTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerSeeds(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            for ($seed = 0; $seed < 20; $seed++) {
                yield $release . ':' . $seed => [$release, (string) $seed];
            }
        }
    }

    /**
     * Repeated account occurrences fail once and preserve release-specific rollback semantics.
     *
     * @throws SqlError When preparing the directly constructed account history fails
     */
    #[DataProvider('providerSeeds')]
    public function testRepeatedAccountsAreExecutedInOrderWithoutSqlInput(string $release, string $seed): void
    {
        $choices = new Choices('revoke:' . $seed);
        $names = array_map(static fn (int $i): string => 'user_' . $i, range(0, $choices->integer(1, 5)));
        $users = array_map(static fn (string $name): UserSpecification => new UserSpecification(new AccountName(new Name($name))), $names);
        $duplicate = $choices->one($names);
        $recipients = $names;
        array_splice($recipients, $choices->integer(0, count($recipients)), 0, [$duplicate]);
        $recipients = array_map(static fn (string $name): UserSpecification => new UserSpecification(new AccountName(new Name($name))), $recipients);
        $privileges = [new StaticPrivilege($choices->one([PrivilegeKind::Select, PrivilegeKind::Insert, PrivilegeKind::Update, PrivilegeKind::Delete]))];
        $level = new DatabaseLevel(new Name('d'));
        $session = (new Instance($release))->connect();
        $execution = new Execution();
        $execution->runIn($session, new CreateUser(false, $users));
        $execution->runIn($session, new GrantPrivileges($privileges, ObjectKind::Table, $level, array_map(static fn (UserSpecification $user): Grantee => Grantee::from($user), $users)));
        $error = null;
        try {
            $execution->runIn($session, new RevokePrivileges(false, $privileges, ObjectKind::Table, $level, $recipients));
        } catch (SqlError $caught) {
            $error = $caught;
        }

        self::assertInstanceOf(SqlError::class, $error);
        self::assertSame(1141, $error->getCode());
        self::assertSame([['Error', 1141, "There is no such grant defined for user '" . $duplicate . "' on host '%'"]], $session->diagnostics->conditions);
        $remaining = array_map(static fn (int $i): bool => $session->instance->accounts->find(new Identity('user_' . $i, '%'))?->grants->databases !== [], array_keys($users));
        self::assertSame(array_fill(0, count($users), !str_starts_with($release, '5.')), $remaining);
    }
}
