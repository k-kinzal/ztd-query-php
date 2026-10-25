<?php

declare(strict_types=1);

namespace Tests\Property\MySql\Statement\Expression\Access;

use JsonException;
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
use SqlSemantics\Platform\MySql\Statement\Expression\Access\OdbcEscape as Model;
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Generator\Bindings;
use Tests\Generator\Choices;
use Tests\Generator\Embedding;

/**
 * Consumes generated analysis results through MemoryDB execution.
 */
#[CoversNothing]
#[Large]
final class OdbcEscapeProperties extends TestCase
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
    public static function providerOdbcTextInputs(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            for ($seed = 0; $seed < 40; $seed++) {
                yield $release . ':' . $seed => [$release, $seed];
            }
        }
    }

    /**
     * Directly constructed escapes execute arbitrary non-temporal decoded text.
     * The caller's unrelated SQL cannot supply or repair the generated operand.
     */
    #[DataProvider('providerOdbcTextInputs')]
    #[Test]
    public function satisfiesNonTemporalStringsExecuteWithoutConstructingInvalidDates(string $release, int $seed): void
    {
        $choices = new Choices('odbc-text:' . $seed);
        $text = 'x';
        $length = $choices->integer(0, 80);
        for ($index = 0; $index < $length; $index++) {
            $text .= chr($choices->integer(32, 126));
        }
        $escape = new Model(new Name($choices->one(['d', 't', 'ts', 'D', 'T', 'TS'])), new StringLiteral([$text]));
        $session = (new Instance($release))->connect();
        $session->text = 'unrelated invalid SQL';
        $statement1 = (new Embedding())->statement($escape);
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        $operation1 = (new Binding())->operation($session, $statement1, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement1)));
        $reply = (new Execution($session))->perform($operation1, (new Dispatcher())->command($operation1->statement), []);
        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertSame([[$text]], $reply->rows);
        self::assertSame($text, $reply->columns[0]->name);
    }

    /**
     * One semantic escape changes its resolved type and column name with calendar settings.
     * The same pre-Operation structure is reused, with no source SQL available to execution.
     */
    #[DataProvider('providerOdbcTextInputs')]
    #[Test]
    public function satisfiesCalendarModesDetermineTheEscapeTypeAndName(string $release, int $seed): void
    {
        $choices = new Choices('odbc-calendar:' . $seed);
        $year = $choices->integer(1000, 9999);
        $text = $choices->one([sprintf('%04d-00-01', $year), sprintf('%04d-02-31', $year), '0000-00-00']);
        $escape = new Model(new Name('d'), new StringLiteral([$text]));
        $session = (new Instance($release))->connect();
        $session->text = 'unrelated invalid SQL';
        $session->variables->session['sql_mode'] = 'NO_ZERO_DATE,NO_ZERO_IN_DATE';
        $statement2 = (new Embedding())->statement($escape);
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        $operation2 = (new Binding())->operation($session, $statement2, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement2)));
        $plain = (new Execution($session))->perform($operation2, (new Dispatcher())->command($operation2->statement), []);
        self::assertInstanceOf(ResultSet::class, $plain);
        self::assertSame([[$text]], $plain->rows);
        self::assertSame($text, $plain->columns[0]->name);
        self::assertSame(Field::VarString, $plain->columns[0]->type);
        $session->variables->session['sql_mode'] = 'ALLOW_INVALID_DATES';
        $statement3 = (new Embedding())->statement($escape);
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        $operation3 = (new Binding())->operation($session, $statement3, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement3)));
        $dated = (new Execution($session))->perform($operation3, (new Dispatcher())->command($operation3->statement), []);
        self::assertInstanceOf(ResultSet::class, $dated);
        self::assertSame([[$text]], $dated->rows);
        self::assertSame("{ d '" . $text . "' }", $dated->columns[0]->name);
        self::assertSame(Field::Date, $dated->columns[0]->type);
    }
}
