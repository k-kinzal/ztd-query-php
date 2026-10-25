<?php

declare(strict_types=1);

namespace Tests\Property\MySql\Statement\Account;

use JsonException;
use MySqlMemory\Account\Identity;
use MySqlMemory\Command\Dispatcher;
use MySqlMemory\Error\SqlError;
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
use SqlSemantics\Platform\MySql\Statement\Account\CreateUser;
use SqlSemantics\Platform\MySql\Statement\Account\RenameUser as Model;
use SqlSemantics\Platform\MySql\Statement\Account\User\UserRenaming;
use SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification;
use SqlSemantics\Platform\MySql\Statement\Call\KeywordCall;
use SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction;
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
final class RenameUserProperties extends TestCase
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
     * @return iterable<string, array{string, int}>
     */
    public static function providerRenameHistories(): iterable
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
    #[DataProvider('providerRenameHistories')]
    #[Test]
    public function satisfiesCurrentUserFollowsItsOccurrencesAndRetainsConnectionState(string $release, int $seed): void
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
        $session1 = $instance->connect();
        $statement1 = (new Embedding())->statement(new CreateUser(false, [new UserSpecification(new AccountName(new Name($user)))]));
        $session1->activity->begin();
        StatementCounters::received($session1);
        $session1->hinted = [];
        $session1->dots = [];
        $operation1 = (new Binding())->operation($session1, $statement1, [], new LanguageProfile($session1->semantics()->profile()->grammar, $session1->semantics()->profile()->lexical, (new Bindings())->parameters($statement1)));
        (new Execution($session1))->perform($operation1, (new Dispatcher())->command($operation1->statement), []);
        $session = $instance->connect($user);
        $other = $instance->connect($user);
        $session->text = 'unrelated invalid SQL';
        $error = null;
        try {
            $statement2 = (new Embedding())->statement(new Model($pairs));
            $session->activity->begin();
            StatementCounters::received($session);
            $session->hinted = [];
            $session->dots = [];
            $operation2 = (new Binding())->operation($session, $statement2, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement2)));
            (new Execution($session))->perform($operation2, (new Dispatcher())->command($operation2->statement), []);
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
        $statement3 = (new Embedding())->statement(new KeywordCall(KeywordFunction::CurrentUser, []));
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        $operation3 = (new Binding())->operation($session, $statement3, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement3)));
        $read = (new Execution($session))->perform($operation3, (new Dispatcher())->command($operation3->statement), []);
        $statement4 = (new Embedding())->statement(new KeywordCall(KeywordFunction::CurrentUser, []));
        $other->activity->begin();
        StatementCounters::received($other);
        $other->hinted = [];
        $other->dots = [];
        $operation4 = (new Binding())->operation($other, $statement4, [], new LanguageProfile($other->semantics()->profile()->grammar, $other->semantics()->profile()->lexical, (new Bindings())->parameters($statement4)));
        $watched = (new Execution($other))->perform($operation4, (new Dispatcher())->command($operation4->statement), []);
        $statement5 = (new Embedding())->statement(new KeywordCall(KeywordFunction::User, []));
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        $operation5 = (new Binding())->operation($session, $statement5, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement5)));
        $login = (new Execution($session))->perform($operation5, (new Dispatcher())->command($operation5->statement), []);
        self::assertInstanceOf(ResultSet::class, $read);
        self::assertInstanceOf(ResultSet::class, $watched);
        self::assertInstanceOf(ResultSet::class, $login);
        self::assertSame([[$legacy ? $user . '@%' : $last->text()]], $read->rows);
        self::assertSame([[$user . '@%']], $watched->rows);
        self::assertSame([[$user . '@localhost']], $login->rows);
    }
}
