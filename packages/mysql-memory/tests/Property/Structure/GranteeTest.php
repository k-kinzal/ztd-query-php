<?php

declare(strict_types=1);

namespace Tests\Property\Structure;

use Fuzz\Structure\Choices;
use Fuzz\Structure\Execution;
use MySqlMemory\Account\Identity;
use MySqlMemory\Instance;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Account\CreateUser;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantPrivileges;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\PrivilegeKind;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\StaticPrivilege;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\GlobalLevel;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectKind;
use SqlSemantics\Platform\MySql\Statement\Account\User\Grantee;
use SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification;
use SqlSemantics\Platform\MySql\Statement\Name\AccountName;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Direct grant operands preserve their account and privilege through execution.
 */
#[CoversNothing]
#[Medium]
final class GranteeTest extends TestCase
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
     * @throws \MySqlMemory\Error\SqlError When the directly constructed statement cannot execute
     */
    #[DataProvider('providerSeeds')]
    public function testGrantUpdatesTheNamedAccountWithoutSqlInput(string $release, string $seed): void
    {
        $choices = new Choices('grantee:' . $seed);
        $user = 'user_' . $choices->integer(0, 10000);
        $host = $choices->one(['%', '127.0.0.1', '::1']);
        $privilege = $choices->one([PrivilegeKind::Select, PrivilegeKind::Insert, PrivilegeKind::Update, PrivilegeKind::Delete]);
        $session = (new Instance($release))->connect();
        $execution = new Execution();
        $execution->runIn($session, new CreateUser(false, [new UserSpecification(new AccountName(new Name($user), new Name($host)))]));
        $statement = new GrantPrivileges([new StaticPrivilege($privilege)], ObjectKind::Table, new GlobalLevel(), [new Grantee(new AccountName(new Name($user), new Name($host)))]);
        $execution->runIn($session, $statement);

        self::assertSame([$privilege->value => true], $session->instance->accounts->find(new Identity($user, $host))?->grants->global->names);
        self::assertSame(['user', 'identification'], array_keys(get_object_vars($statement->grantees[0])));
    }
}
