<?php

declare(strict_types=1);

namespace Tests\Property\MySql\Statement\Account;

use JsonException;
use MySqlMemory\Account\Identity;
use MySqlMemory\Command\Dispatcher;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\Execution;
use MySqlMemory\Session\State\StatementCounters;
use MySqlMemory\Session\Statement\Binding;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Large;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Statement\Account\CreateUser as Model;
use SqlSemantics\Platform\MySql\Statement\Account\DropUser;
use SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification;
use SqlSemantics\Platform\MySql\Statement\Name\AccountName;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowGrants;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Generator\Bindings;
use Tests\Generator\Choices;
use Tests\Generator\Embedding;

/**
 * Consumes generated analysis results through MemoryDB execution.
 */
#[CoversNothing]
#[Large]
final class CreateUserProperties extends TestCase
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
        } catch (\MySqlMemory\Error\SqlError $error) {
            self::assertTrue($error->signalled !== null || !in_array($error->error, [\MySqlMemory\Error\Family\StatementError::UnknownError, \MySqlMemory\Error\Family\StatementError::NotSupportedYet], true), self::MODEL . ': ' . $error->getMessage());
        }
    }
    /**
     * The semantic value sampled by this property.
     */
    public const MODEL = Model::class;
    /**
     * @return iterable<string, array{string, int}>
     */
    public static function providerWhitespaceHistories(): iterable
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
    #[DataProvider('providerWhitespaceHistories')]
    #[Test]
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
        $statement1 = (new Embedding())->statement(new Model(false, [new UserSpecification(new AccountName(new Name($prefix . $user . $suffix), new Name($suffix . $host . $prefix)))]));
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        $operation1 = (new Binding())->operation($session, $statement1, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement1)));
        (new Execution($session))->perform($operation1, (new Dispatcher())->command($operation1->statement), []);
        $statement2 = (new Embedding())->statement(new ShowGrants(new AccountName(new Name($user), new Name(strtolower($host)))));
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        $operation2 = (new Binding())->operation($session, $statement2, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement2)));
        $result = (new Execution($session))->perform($operation2, (new Dispatcher())->command($operation2->statement), []);
        self::assertInstanceOf(ResultSet::class, $result);
        self::assertCount(1, $result->rows);
        self::assertNotNull($session->instance->accounts->find(Identity::of($user, $host)));
        $statement3 = (new Embedding())->statement(new DropUser(false, [new AccountName(new Name($suffix . $user . $prefix), new Name($prefix . $host . $suffix))]));
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        $operation3 = (new Binding())->operation($session, $statement3, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement3)));
        (new Execution($session))->perform($operation3, (new Dispatcher())->command($operation3->statement), []);
        self::assertNull($session->instance->accounts->find(Identity::of($user, $host)));
    }
}
