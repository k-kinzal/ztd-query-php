<?php

declare(strict_types=1);

namespace Tests\Property\MySql\Statement\Account;

use JsonException;
use MySqlMemory\Command\Dispatcher;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
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
use SqlSemantics\Platform\MySql\Statement\Account\Password;
use SqlSemantics\Platform\MySql\Statement\Account\PasswordFunction;
use SqlSemantics\Platform\MySql\Statement\Account\SetPassword as Model;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Name\AccountName;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Generator\Bindings;
use Tests\Generator\Choices;
use Tests\Generator\Embedding;

/**
 * Consumes generated analysis results through MemoryDB execution.
 */
#[CoversNothing]
#[Large]
final class SetPasswordProperties extends TestCase
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
        $parameters = array_fill(0, (new Bindings())->count($operation->statement), [null, Domain::null()]);
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
     * @return iterable<string, array{Model}>
     */
    public static function providerPasswordNoticesRequests(): iterable
    {
        $choices = new Choices('password-recipient-20261017');
        for ($trial = 0; $trial < 40; $trial++) {
            yield (string) $trial => [new Model(new AccountName(new Name('u'), new Name('localhost')), new Password(new Text((string) $choices->integer(0, 100000)), PasswordFunction::OldPassword))];
        }
    }

    /**
     * An error caused by the recipient's authentication format is not a scalar construction defect.
     */
    #[DataProvider('providerPasswordNoticesRequests')]
    #[Test]
    public function satisfiesUnchangedPasswordFollowsTheRecipientPlugin(Model $statement): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $session->text = 'unrelated invalid input';
        $statement1 = (new Embedding())->statement($statement);
        $parameters1 = array_fill(0, (new Bindings())->count($statement1), [null, Domain::null()]);
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        try {
            $operation1 = (new Binding())->operation($session, $statement1, $parameters1, new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement1)));
            $result1 = (new Execution($session))->perform($operation1, (new Dispatcher())->command($operation1->statement), $parameters1);
        } catch (SqlError $executionError) {
            $result1 = $executionError;
            (new Execution($session))->failed($executionError);
        }
        $missing = $result1;
        $session->query('CREATE USER u@localhost IDENTIFIED WITH mysql_old_password');
        $session->text = 'unrelated invalid input';
        $statement2 = (new Embedding())->statement($statement);
        $parameters2 = array_fill(0, (new Bindings())->count($statement2), [null, Domain::null()]);
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        try {
            $operation2 = (new Binding())->operation($session, $statement2, $parameters2, new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement2)));
            $result2 = (new Execution($session))->perform($operation2, (new Dispatcher())->command($operation2->statement), $parameters2);
        } catch (SqlError $executionError) {
            $result2 = $executionError;
            (new Execution($session))->failed($executionError);
        }
        $warnings2 = array_map(static fn (array $condition): array => [$condition[0], (string) $condition[1], $condition[2]], $session->diagnostics->conditions);
        $old = $result2;
        $session->query("DROP USER u@localhost; CREATE USER u@localhost IDENTIFIED BY 'pw'");
        $session->text = 'unrelated invalid input';
        $statement3 = (new Embedding())->statement($statement);
        $parameters3 = array_fill(0, (new Bindings())->count($statement3), [null, Domain::null()]);
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        try {
            $operation3 = (new Binding())->operation($session, $statement3, $parameters3, new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement3)));
            $result3 = (new Execution($session))->perform($operation3, (new Dispatcher())->command($operation3->statement), $parameters3);
        } catch (SqlError $executionError) {
            $result3 = $executionError;
            (new Execution($session))->failed($executionError);
        }
        $warnings3 = array_map(static fn (array $condition): array => [$condition[0], (string) $condition[1], $condition[2]], $session->diagnostics->conditions);
        $modern = $result3;
        self::assertSame(1133, ($missing instanceof SqlError ? [$missing->getCode(), $missing->sqlState(), $missing->getMessage()] : null)[0] ?? null);
        self::assertNotInstanceOf(SqlError::class, $old);
        self::assertSame(['1287', '1287'], array_column($warnings2, 1));
        self::assertSame(1372, ($modern instanceof SqlError ? [$modern->getCode(), $modern->sqlState(), $modern->getMessage()] : null)[0] ?? null);
        self::assertSame(['1287', '1372'], array_column($warnings3, 1));
        self::assertSame('unrelated invalid input', $session->text);
    }
}
