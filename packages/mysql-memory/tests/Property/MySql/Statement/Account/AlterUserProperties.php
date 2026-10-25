<?php

declare(strict_types=1);

namespace Tests\Property\MySql\Statement\Account;

use JsonException;
use MySqlMemory\Account\Identity;
use MySqlMemory\Command\Dispatcher;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
use MySqlMemory\Session\Execution;
use MySqlMemory\Session\State\StatementCounters;
use MySqlMemory\Session\Statement\Binding;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Statement\Account\AlterUser as Model;
use SqlSemantics\Platform\MySql\Statement\Account\CreateUser;
use SqlSemantics\Platform\MySql\Statement\Account\DropUser;
use SqlSemantics\Platform\MySql\Statement\Account\User\Credential;
use SqlSemantics\Platform\MySql\Statement\Account\User\Factor;
use SqlSemantics\Platform\MySql\Statement\Account\User\FactorAction;
use SqlSemantics\Platform\MySql\Statement\Account\User\FactorChange;
use SqlSemantics\Platform\MySql\Statement\Account\User\FactorStep;
use SqlSemantics\Platform\MySql\Statement\Account\User\Identification;
use SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification;
use SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Name\AccountName;
use SqlSemantics\Platform\MySql\Statement\Name\CurrentUser;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Generator\Bindings;
use Tests\Generator\Choices;
use Tests\Generator\Embedding;

/**
 * Consumes generated analysis results through MemoryDB execution.
 */
#[CoversNothing]
#[Large]
final class AlterUserProperties extends TestCase
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
        $mode = (new \Tests\Generator\Profiles())->mode($profile);
        $session = (new Instance(substr($profile->grammar->value, 6), ['sql_mode' => $mode], [$database]))->connect(database: $database);
        $parameters = array_fill(0, (new Bindings())->count($operation->statement), [null, \MySqlMemory\Typing\Domain::null()]);
        try {
            $reply = (new Execution($session))->perform($operation, (new Dispatcher())->command($operation->statement), $parameters);
            self::assertGreaterThanOrEqual(0, $reply->warnings());
        } catch (SqlError $error) {
            self::assertTrue($error->signalled !== null || !in_array($error->error, [\MySqlMemory\Error\Family\StatementError::UnknownError, \MySqlMemory\Error\Family\StatementError::NotSupportedYet], true), self::MODEL . ': ' . $error->getMessage());
        }
    }
    /**
     * The semantic value sampled by this property.
     */
    public const MODEL = Model::class;
    /**
     * Reuses CURRENT_USER unchanged across removal and recreation of its account.
     */
    #[DataProvider('providerAuthenticationFactorHistories')]
    #[Test]
    public function satisfiesCurrentUserRetainsPrimaryAuthenticationAcrossAnAccountTransition(string $release, int $seed): void
    {
        $choices = new Choices('current-user-factor:' . $seed);
        $account = new CurrentUser($choices->one(OptionalWords::cases()));
        $method = static fn () => new Identification(null, Credential::Password, new Text('password_' . $choices->integer(0, 100000)));
        $factors = array_map(static fn () => $method(), range(1, $choices->integer(1, 2)));
        $statement = new CreateUser(false, [new UserSpecification($account, $method(), $factors)]);
        $session = (new Instance($release))->connect();
        $session->text = 'unrelated invalid source';
        try {
            $statement1 = (new Embedding())->statement($statement);
            $session->activity->begin();
            StatementCounters::received($session);
            $session->hinted = [];
            $session->dots = [];
            $operation1 = (new Binding())->operation($session, $statement1, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement1)));
            (new Execution($session))->perform($operation1, (new Dispatcher())->command($operation1->statement), []);
            self::fail('The connected account already exists.');
        } catch (SqlError $failure) {
            self::assertSame(1396, $failure->getCode());
            self::assertSame([], $session->diagnostics->conditions);
        }
        $statement2 = (new Embedding())->statement(new DropUser(false, [$account]));
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        $operation2 = (new Binding())->operation($session, $statement2, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement2)));
        (new Execution($session))->perform($operation2, (new Dispatcher())->command($operation2->statement), []);
        $statement3 = (new Embedding())->statement($statement);
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        $operation3 = (new Binding())->operation($session, $statement3, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement3)));
        $reply = (new Execution($session))->perform($operation3, (new Dispatcher())->command($operation3->statement), []);
        self::assertInstanceOf(Completion::class, $reply);
        self::assertSame(0, $reply->warnings());
        self::assertNotNull($session->instance->accounts->find(new Identity($session->user, $session->host)));
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function providerAuthenticationFactorHistories(): iterable
    {
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            for ($seed = 0; $seed < 20; $seed++) {
                yield $release . ':' . $seed => [$release, $seed];
            }
        }
    }

    /**
     * Reuses a generated factor change before and after its account is created.
     */
    #[DataProvider('providerAuthenticationFactorHistories')]
    #[Test]
    public function satisfiesTypedFactorsDistinguishMissingAccountsFromMissingFactors(string $release, int $seed): void
    {
        $choices = new Choices('authentication-factor:' . $seed);
        $factor = $choices->one(Factor::cases());
        $account = new AccountName(new Name('factor_user_' . $choices->integer(0, 100000)));
        $statement = new Model(false, [new FactorChange($account, FactorAction::Drop, [new FactorStep($factor)])]);
        $session = (new Instance($release))->connect();
        $session->text = 'unrelated invalid source';
        $errors = [];
        foreach ([false, true] as $exists) {
            if ($exists) {
                $statement4 = (new Embedding())->statement(new CreateUser(false, [new UserSpecification($account)]));
                $session->activity->begin();
                StatementCounters::received($session);
                $session->hinted = [];
                $session->dots = [];
                $operation4 = (new Binding())->operation($session, $statement4, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement4)));
                (new Execution($session))->perform($operation4, (new Dispatcher())->command($operation4->statement), []);
            }
            try {
                $statement5 = (new Embedding())->statement($statement);
                $session->activity->begin();
                StatementCounters::received($session);
                $session->hinted = [];
                $session->dots = [];
                $operation5 = (new Binding())->operation($session, $statement5, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement5)));
                (new Execution($session))->perform($operation5, (new Dispatcher())->command($operation5->statement), []);
                self::fail('No installed additional authentication factor exists in this fixture.');
            } catch (SqlError $failure) {
                $errors[] = [$failure->getCode(), $failure->getMessage()];
            }
        }
        self::assertSame([[1524, "Plugin '' is not loaded"], [4057, $factor->value . " factor authentication method doesn't exist. Please do ALTER USER... ADD " . $factor->value . ' factor... before doing this operation.']], $errors);
    }
}
