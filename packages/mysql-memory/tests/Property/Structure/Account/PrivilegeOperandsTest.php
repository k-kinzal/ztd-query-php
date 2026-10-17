<?php

declare(strict_types=1);

namespace Tests\Property\Structure\Account;

use Fuzz\Structure\Catalogue;
use Fuzz\Structure\Choices;
use Fuzz\Structure\Construction;
use Fuzz\Structure\Execution;
use Fuzz\Structure\Metadata;
use JsonException;
use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use SqlSemantics\Platform\MySql\Statement\Account\CreateUser;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\Privilege;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\StaticPrivilege;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\GlobalLevel;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectKind;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectLevel;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\RevokePrivileges;
use SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification;
use SqlSemantics\Platform\MySql\Statement\Name\AccountName;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;

/**
 * Constructor-generated privilege operands execute under REVOKE IF EXISTS with an existing recipient.
 */
#[CoversNothing]
#[Medium]
final class PrivilegeOperandsTest extends TestCase
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
     * @throws ReflectionException When constructor types cannot be inspected
     */
    #[DataProvider('providerInputs')]
    public function testGeneratedPrivilegeOperandsRevokeWithoutInputSql(string $release, string $seed): void
    {
        $privilege = (new Construction(new Choices('privilege-operands:' . $seed), new Catalogue(), new Metadata()))->object(Privilege::class, 6);
        self::assertInstanceOf(Privilege::class, $privilege);
        $user = new AccountName(new Name('privilege_recipient_fixture'));
        $session = (new Instance($release, [], ['d']))->connect('root', 'localhost', 'd');
        $session->text = 'unrelated invalid input';
        $execution = new Execution();
        $execution->runIn($session, new CreateUser(false, [new UserSpecification($user)]));
        $level = $privilege instanceof StaticPrivilege && $privilege->columns !== [] ? new ObjectLevel(new QualifiedName(new Name('t'), new Name('d'))) : new GlobalLevel();
        $revoke = new RevokePrivileges(true, [$privilege], ObjectKind::Table, $level, [new UserSpecification($user)]);

        self::assertInstanceOf(Completion::class, $execution->runIn($session, $revoke));
        self::assertSame([], array_values(array_filter($session->diagnostics->conditions, static fn (array $condition): bool => $condition[0] === 'Error')));

    }
}
