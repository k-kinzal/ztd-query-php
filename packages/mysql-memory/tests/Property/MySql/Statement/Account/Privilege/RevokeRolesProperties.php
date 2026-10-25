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
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\GrantedRole;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\RevokeRoles as Model;
use SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification;
use SqlSemantics\Platform\MySql\Statement\Name\AccountName;
use SqlSemantics\Statement\Identifier\Name;
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
final class RevokeRolesProperties extends TestCase
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
    public static function providerRoleOperandsInputs(): iterable
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
    #[DataProvider('providerRoleOperandsInputs')]
    #[Test]
    public function satisfiesGeneratedRoleOperandsRevokeWithoutInputSql(string $release, string $seed): void
    {
        $role = (new Construction(new Choices('role-operands:' . $seed), new Catalogue(), new Metadata()))->object(GrantedRole::class, 6);
        self::assertInstanceOf(GrantedRole::class, $role);
        $user = new AccountName(new Name('role_recipient_fixture'));
        $session = (new Instance($release))->connect();
        $session->text = 'unrelated invalid input';
        $statement1 = (new Embedding())->statement(new CreateUser(false, [new UserSpecification($role->role)]));
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        $operation1 = (new Binding())->operation($session, $statement1, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement1)));
        (new Execution($session))->perform($operation1, (new Dispatcher())->command($operation1->statement), []);
        $statement2 = (new Embedding())->statement(new CreateUser(false, [new UserSpecification($user)]));
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        $operation2 = (new Binding())->operation($session, $statement2, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement2)));
        (new Execution($session))->perform($operation2, (new Dispatcher())->command($operation2->statement), []);
        $revoke = new Model(false, [$role], [$user]);
        $statement3 = (new Embedding())->statement($revoke);
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        $operation3 = (new Binding())->operation($session, $statement3, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement3)));
        self::assertInstanceOf(Completion::class, (new Execution($session))->perform($operation3, (new Dispatcher())->command($operation3->statement), []));
        self::assertSame([], $session->instance->accounts->roles(new Identity($user->username(), '%')));
        self::assertSame([], $session->diagnostics->conditions);
    }

    /**
     * @throws JsonException When the value inventory is invalid
     */
    #[Test]
    public function satisfiesGeneratedRoleRevocationCanBeConsumedWithNonemptyCollections(): void
    {
        $catalogue = new Catalogue();
        $choices = new Choices('ffa5f501db4aaf2f94844bca78c9dc8f:246');
        $choices->one($catalogue->roots());
        $construction = new Construction($choices, $catalogue, new Metadata());
        $class = Model::class;
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
