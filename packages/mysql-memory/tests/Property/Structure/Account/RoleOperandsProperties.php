<?php

declare(strict_types=1);

namespace Tests\Property\Structure\Account;

use JsonException;
use MySqlMemory\Account\Identity;
use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Account\CreateUser;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\GrantedRole;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\RevokeRoles;
use SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification;
use SqlSemantics\Platform\MySql\Statement\Name\AccountName;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Fake\MemoryClient as Execution;
use Tests\Generator\Catalogue;
use Tests\Generator\Choices;
use Tests\Generator\Construction;
use Tests\Generator\Metadata;

/**
 * Constructor-generated role operands execute with an existing, distinct recipient.
 */
#[CoversNothing]
#[Medium]
final class RoleOperandsProperties extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerInputs(): iterable
    {
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            for ($seed = 0; $seed < 30; $seed++) {
                yield $release . ':' . $seed => [$release, (string) $seed];
            }
        }
    }

    /**
     * @throws JsonException When the value inventory is invalid
     */
    #[DataProvider('providerInputs')]
    #[\PHPUnit\Framework\Attributes\Test]
    public function satisfiesGeneratedRoleOperandsRevokeWithoutInputSql(string $release, string $seed): void
    {
        $role = (new Construction(new Choices('role-operands:' . $seed), new Catalogue(), new Metadata()))->object(GrantedRole::class, 6);
        self::assertInstanceOf(GrantedRole::class, $role);
        $user = new AccountName(new Name('role_recipient_fixture'));
        $session = (new Instance($release))->connect();
        $session->text = 'unrelated invalid input';
        $execution = new Execution();
        $execution->runIn($session, new CreateUser(false, [new UserSpecification($role->role)]));
        $execution->runIn($session, new CreateUser(false, [new UserSpecification($user)]));
        $revoke = new RevokeRoles(false, [$role], [$user]);

        self::assertInstanceOf(Completion::class, $execution->runIn($session, $revoke));
        self::assertSame([], $session->instance->accounts->roles(new Identity($user->username(), '%')));
        self::assertSame([], $session->diagnostics->conditions);
    }
}
