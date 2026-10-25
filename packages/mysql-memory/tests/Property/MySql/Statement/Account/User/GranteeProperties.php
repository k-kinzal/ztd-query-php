<?php

declare(strict_types=1);

namespace Tests\Property\MySql\Statement\Account\User;

use JsonException;
use MySqlMemory\Account\Identity;
use MySqlMemory\Command\Dispatcher;
use MySqlMemory\Instance;
use MySqlMemory\Session\Execution;
use MySqlMemory\Session\State\StatementCounters;
use MySqlMemory\Session\Statement\Binding;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Statement\Account\CreateUser;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantPrivileges;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\PrivilegeKind;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\StaticPrivilege;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\GlobalLevel;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectKind;
use SqlSemantics\Platform\MySql\Statement\Account\User\Grantee as Model;
use SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification;
use SqlSemantics\Platform\MySql\Statement\Name\AccountName;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Generator\Bindings;
use Tests\Generator\Choices;
use Tests\Generator\Embedding;

/**
 * Samples the public semantic value and checks its constructor contract.
 */
#[CoversNothing]
#[Large]
final class GranteeProperties extends TestCase
{
    /**
     * @return iterable<string, array{non-negative-int}>
     */
    public static function providerSamples(): iterable
    {
        for ($sample = 0; $sample < (new \Tests\Generator\Samples())->count(); $sample++) {
            yield 'sample ' . $sample => [$sample];
        }
    }
    /**
     * @throws JsonException When a dependency inventory cannot be read
     * Every sampled constructor accepts arguments within its declared invariant domain.
     * @param non-negative-int $sample
     */
    #[Test]
    #[DataProvider('providerSamples')]
    public function satisfiesPublicValueInvariants(int $sample): void
    {
        $value = (new \Tests\Generator\Samples())->sample(self::MODEL, $sample);
        self::assertInstanceOf(self::MODEL, $value);
    }
    /**
     * The semantic value sampled by this property.
     */
    public const MODEL = Model::class;
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerGranteeSeeds(): iterable
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
    #[DataProvider('providerGranteeSeeds')]
    #[Test]
    public function satisfiesGrantUpdatesTheNamedAccountWithoutSqlInput(string $release, string $seed): void
    {
        $choices = new Choices('grantee:' . $seed);
        $user = 'user_' . $choices->integer(0, 10000);
        $host = $choices->one(['%', '127.0.0.1', '::1']);
        $privilege = $choices->one([PrivilegeKind::Select, PrivilegeKind::Insert, PrivilegeKind::Update, PrivilegeKind::Delete]);
        $session = (new Instance($release))->connect();
        $statement1 = (new Embedding())->statement(new CreateUser(false, [new UserSpecification(new AccountName(new Name($user), new Name($host)))]));
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        $operation1 = (new Binding())->operation($session, $statement1, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement1)));
        (new Execution($session))->perform($operation1, (new Dispatcher())->command($operation1->statement), []);
        $statement = new GrantPrivileges([new StaticPrivilege($privilege)], ObjectKind::Table, new GlobalLevel(), [new Model(new AccountName(new Name($user), new Name($host)))]);
        $statement2 = (new Embedding())->statement($statement);
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        $operation2 = (new Binding())->operation($session, $statement2, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement2)));
        (new Execution($session))->perform($operation2, (new Dispatcher())->command($operation2->statement), []);
        self::assertSame([$privilege->value => true], $session->instance->accounts->find(new Identity($user, $host))?->grants->global->names);
        self::assertSame(['user', 'identification'], array_keys(get_object_vars($statement->grantees[0])));
    }
}
