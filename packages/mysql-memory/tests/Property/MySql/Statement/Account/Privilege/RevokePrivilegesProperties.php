<?php

declare(strict_types=1);

namespace Tests\Property\MySql\Statement\Account\Privilege;

use JsonException;
use MySqlMemory\Account\Identity;
use MySqlMemory\Command\Dispatcher;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use MySqlMemory\Session\Execution;
use MySqlMemory\Session\State\StatementCounters;
use MySqlMemory\Session\Statement\Binding;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Statement\Account\CreateUser;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantPrivileges;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\Privilege;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\PrivilegeKind;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\StaticPrivilege;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\DatabaseLevel;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\GlobalLevel;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectKind;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectLevel;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\RevokePrivileges as Model;
use SqlSemantics\Platform\MySql\Statement\Account\User\Grantee;
use SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification;
use SqlSemantics\Platform\MySql\Statement\Name\AccountName;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Tests\Generator\Bindings;
use Tests\Generator\Catalogue;
use Tests\Generator\Choices;
use Tests\Generator\Construction;
use Tests\Generator\Embedding;
use Tests\Generator\Metadata;
use Tests\Generator\Profiles;

/**
 * Consumes generated analysis results through MemoryDB execution.
 */
#[CoversNothing]
#[Large]
final class RevokePrivilegesProperties extends TestCase
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
     * Runs the generated analysis result through the ordinary dispatch and execution boundary.
     * @param non-negative-int $sample
     * @throws JsonException When the semantic type inventory cannot be read
     */
    #[Test]
    #[DataProvider('providerSamples')]
    public function satisfiesPublicValueInvariants(int $sample): void
    {
        $value = (new \Tests\Generator\Samples())->sample(self::MODEL, $sample);
        $statement = (new Embedding())->statement($value);
        $operation = (new \Tests\Generator\Semantics\Statement\OperationGenerator())->from($statement, new Choices('context:' . $sample));
        $profile = $operation->profile();
        $database = $operation->context->searchPath[0]->value;
        $mode = (new Profiles())->mode($profile);
        $session = (new Instance(substr($profile->grammar->value, 6), ['sql_mode' => $mode], [$database]))->connect(database: $database);
        $parameters = array_fill(0, (new Bindings())->count($operation->statement), [null, Domain::null()]);
        try {
            $reply = (new Execution($session))->perform($operation, (new Dispatcher())->command($operation->statement), $parameters);
            self::assertGreaterThanOrEqual(0, $reply->warnings());
        } catch (SqlError $error) {
            self::assertTrue($error->signalled !== null || !in_array($error->error, [StatementError::UnknownError, StatementError::NotSupportedYet], true), self::MODEL . ': ' . $error->getMessage());
        }
    }
    /**
     * The semantic value sampled by this property.
     */
    public const MODEL = Model::class;
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerRevokeSeeds(): iterable
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
    #[DataProvider('providerRevokeSeeds')]
    #[Test]
    public function satisfiesRepeatedAccountsAreExecutedInOrderWithoutSqlInput(string $release, string $seed): void
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
        $statement1 = (new Embedding())->statement(new CreateUser(false, $users));
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        $operation1 = (new Binding())->operation($session, $statement1, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement1)));
        (new Execution($session))->perform($operation1, (new Dispatcher())->command($operation1->statement), []);
        $statement2 = (new Embedding())->statement(new GrantPrivileges($privileges, ObjectKind::Table, $level, array_map(static fn (UserSpecification $user): Grantee => Grantee::from($user), $users)));
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        $operation2 = (new Binding())->operation($session, $statement2, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement2)));
        (new Execution($session))->perform($operation2, (new Dispatcher())->command($operation2->statement), []);
        $error = null;
        try {
            $statement3 = (new Embedding())->statement(new Model(false, $privileges, ObjectKind::Table, $level, $recipients));
            $session->activity->begin();
            StatementCounters::received($session);
            $session->hinted = [];
            $session->dots = [];
            $operation3 = (new Binding())->operation($session, $statement3, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement3)));
            (new Execution($session))->perform($operation3, (new Dispatcher())->command($operation3->statement), []);
        } catch (SqlError $caught) {
            $error = $caught;
        }
        self::assertInstanceOf(SqlError::class, $error);
        self::assertSame(1141, $error->getCode());
        self::assertSame([['Error', 1141, "There is no such grant defined for user '" . $duplicate . "' on host '%'"]], $session->diagnostics->conditions);
        $remaining = array_map(static fn (int $i): bool => $session->instance->accounts->find(new Identity('user_' . $i, '%'))?->grants->databases !== [], array_keys($users));
        self::assertSame(array_fill(0, count($users), !str_starts_with($release, '5.')), $remaining);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerPrivilegeOperandsInputs(): iterable
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
    #[DataProvider('providerPrivilegeOperandsInputs')]
    #[Test]
    public function satisfiesGeneratedPrivilegeOperandsRevokeWithoutInputSql(string $release, string $seed): void
    {
        $privilege = (new Construction(new Choices('privilege-operands:' . $seed), new Catalogue(), new Metadata()))->object(Privilege::class, 6);
        self::assertInstanceOf(Privilege::class, $privilege);
        $user = new AccountName(new Name('privilege_recipient_fixture'));
        $session = (new Instance($release, [], ['d']))->connect('root', 'localhost', 'd');
        $session->text = 'unrelated invalid input';
        $statement1 = (new Embedding())->statement(new CreateUser(false, [new UserSpecification($user)]));
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        $operation1 = (new Binding())->operation($session, $statement1, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement1)));
        (new Execution($session))->perform($operation1, (new Dispatcher())->command($operation1->statement), []);
        $level = $privilege instanceof StaticPrivilege && $privilege->columns !== [] ? new ObjectLevel(new QualifiedName(new Name('t'), new Name('d'))) : new GlobalLevel();
        $revoke = new Model(true, [$privilege], ObjectKind::Table, $level, [new UserSpecification($user)]);
        $statement2 = (new Embedding())->statement($revoke);
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        $operation2 = (new Binding())->operation($session, $statement2, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement2)));
        self::assertInstanceOf(Completion::class, (new Execution($session))->perform($operation2, (new Dispatcher())->command($operation2->statement), []));
        self::assertSame([], array_values(array_filter($session->diagnostics->conditions, static fn (array $condition): bool => $condition[0] === 'Error')));
    }

    /**
     * @throws JsonException When the value inventory is invalid
     */
    #[Test]
    public function satisfiesGeneratedPrivilegeRootCanBeConsumedWithoutReplacingItsType(): void
    {
        $catalogue = new Catalogue();
        $choices = new Choices('2674a4091ceacb6852ced919ff7dec51:402');
        $choices->one($catalogue->roots());
        $class = Model::class;
        $construction = new Construction($choices, $catalogue, new Metadata());
        $node = $construction->object($class, 6);
        self::assertInstanceOf($class, $node);
        $subject = $node;
        $profile = (new Profiles())->select($subject);
        $session = (new Instance(substr($profile->grammar->value, 6)))->connect();
        $session->variables->session['sql_mode'] = (new Profiles())->mode($profile);
        $statement = (new Embedding())->statement($subject);
        $parameters = array_fill(0, (new Bindings())->count($statement), [null, Domain::null()]);
        try {
            $operation = (new Binding())->operation($session, $statement, $parameters, $profile);
            $reply = (new Execution($session))->perform($operation, (new Dispatcher())->command($operation->statement), $parameters);
            self::assertGreaterThanOrEqual(0, $reply->warnings());
        } catch (SqlError $failure) {
            self::assertTrue($failure->signalled !== null || !in_array($failure->error, [StatementError::UnknownError, StatementError::NotSupportedYet], true), $failure->getMessage());
        }
    }
}
