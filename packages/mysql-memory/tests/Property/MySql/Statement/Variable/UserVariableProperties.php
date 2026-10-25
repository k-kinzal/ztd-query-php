<?php

declare(strict_types=1);

namespace Tests\Property\MySql\Statement\Variable;

use JsonException;
use MySqlMemory\Account\Identity;
use MySqlMemory\Command\Dispatcher;
use MySqlMemory\Instance;
use MySqlMemory\Result\Completion;
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
use SqlSemantics\Platform\MySql\Statement\Account\Password;
use SqlSemantics\Platform\MySql\Statement\Account\PasswordFunction;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\Text;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics\GetDiagnostics;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics\InformationItem;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics\StatementDiagnostics;
use SqlSemantics\Platform\MySql\Statement\Routine\Condition\Diagnostics\StatementItemName;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\PasswordAssignment;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\UserAssignment;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable as Model;
use SqlSemantics\Platform\MySql\Statement\Variable\VariableAssignment;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Generator\Bindings;
use Tests\Generator\Choices;
use Tests\Generator\Embedding;

/**
 * Consumes generated analysis results through MemoryDB execution.
 */
#[CoversNothing]
#[Large]
final class UserVariableProperties extends TestCase
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
     * @return iterable<string, array{string, string}>
     */
    public static function providerUserVariableDiagnosticNames(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            for ($seed = 0; $seed < 20; $seed++) {
                $choices = new Choices('diagnostic-name:' . $release . ':' . $seed);
                $length = $choices->integer($release === '5.6.51' ? 0 : 1, $release === '5.6.51' ? 75 : 64);
                yield $release . ':' . $seed => [$release, str_repeat($choices->one(['a', 'é', '界', '0']), $length)];
            }
        }
    }

    /**
     * Constructed diagnostic assignments accept character-bounded multibyte names.
     */
    #[DataProvider('providerUserVariableDiagnosticNames')]
    #[Test]
    public function satisfiesConstructedDiagnosticsAssignCharacterBoundedNames(string $release, string $name): void
    {
        $session = (new Instance($release))->connect();
        $statement1 = (new Embedding())->statement(new SetVariables([new UserAssignment(new Model(new Name($name)), new NumberLiteral('7'))]));
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        $operation1 = (new Binding())->operation($session, $statement1, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement1)));
        (new Execution($session))->perform($operation1, (new Dispatcher())->command($operation1->statement), []);
        $statement = new GetDiagnostics(new StatementDiagnostics([new InformationItem(new Model(new Name($name)), StatementItemName::Number)]));
        $statement2 = (new Embedding())->statement($statement);
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        $operation2 = (new Binding())->operation($session, $statement2, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement2)));
        $reply = (new Execution($session))->perform($operation2, (new Dispatcher())->command($operation2->statement), []);
        $statement3 = (new Embedding())->statement(new Model(new Name($name)));
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        $operation3 = (new Binding())->operation($session, $statement3, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement3)));
        $read = (new Execution($session))->perform($operation3, (new Dispatcher())->command($operation3->statement), []);
        self::assertInstanceOf(Completion::class, $reply);
        self::assertInstanceOf(ResultSet::class, $read);
        self::assertSame([['0']], $read->rows);
        self::assertSame([], $session->diagnostics->conditions);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerUserVariablePasswordAssignments(): iterable
    {
        for ($seed = 0; $seed < 20; $seed++) {
            $choices = new Choices('password-assignment:' . $seed);
            yield (string) $seed => [$choices->one(['', 'secret', "a'b", '日本語', '\\']), (string) $choices->integer(0, 1000)];
        }
    }

    /**
     * A legacy password item and an ordinary assignment execute from constructed operands.
     */
    #[DataProvider('providerUserVariablePasswordAssignments')]
    #[Test]
    public function satisfiesConstructedPasswordItemsExecuteWithVariableAssignments(string $password, string $value): void
    {
        $session = (new Instance('5.6.51'))->connect();
        $statement = new SetVariables([new PasswordAssignment(new Password(new Text($password), PasswordFunction::Password)), new UserAssignment(new Model(new Name('value')), new NumberLiteral($value))]);
        $statement4 = (new Embedding())->statement($statement);
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        $operation4 = (new Binding())->operation($session, $statement4, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement4)));
        $write = (new Execution($session))->perform($operation4, (new Dispatcher())->command($operation4->statement), []);
        $statement5 = (new Embedding())->statement(new Model(new Name('value')));
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        $operation5 = (new Binding())->operation($session, $statement5, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement5)));
        $read = (new Execution($session))->perform($operation5, (new Dispatcher())->command($operation5->statement), []);
        self::assertInstanceOf(Completion::class, $write);
        self::assertInstanceOf(ResultSet::class, $read);
        self::assertSame([[$value]], $read->rows);
        self::assertSame($password, $session->instance->accounts->find(new Identity('root', '%'))?->password);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerUserVariableNames(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            foreach (['0', '1', '-1', '9223372036854775807', '9223372036854775808', '001', '1.5'] as $name) {
                yield $release . ':' . $name => [$release, $name];
            }
        }
    }

    /**
     * Numeric names remain SQL strings even when PHP stores their map keys as integers.
     */
    #[DataProvider('providerUserVariableNames')]
    #[Test]
    public function satisfiesConstructedAssignmentsAndReadsKeepNumericNames(string $release, string $name): void
    {
        $value = (string) (new Choices($release . ':' . $name))->integer(0, 1000);
        $session = (new Instance($release))->connect();
        $statement6 = (new Embedding())->statement(new VariableAssignment(new Model(new Name($name)), new NumberLiteral($value)));
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        $operation6 = (new Binding())->operation($session, $statement6, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement6)));
        $write = (new Execution($session))->perform($operation6, (new Dispatcher())->command($operation6->statement), []);
        $statement7 = (new Embedding())->statement(new Model(new Name($name)));
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        $operation7 = (new Binding())->operation($session, $statement7, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement7)));
        $read = (new Execution($session))->perform($operation7, (new Dispatcher())->command($operation7->statement), []);
        self::assertInstanceOf(ResultSet::class, $write);
        self::assertInstanceOf(ResultSet::class, $read);
        self::assertSame([[$value]], $write->rows);
        self::assertSame($write->rows, $read->rows);
    }
}
