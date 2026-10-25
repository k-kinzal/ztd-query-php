<?php

declare(strict_types=1);

namespace Tests\Property\MySql\Statement\Literal;

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
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral as Model;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Relation\DerivedTable;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Generator\Bindings;
use Tests\Generator\Choices;
use Tests\Generator\Embedding;

/**
 * Consumes generated analysis results through MemoryDB execution.
 */
#[CoversNothing]
#[Large]
final class StringLiteralProperties extends TestCase
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
     * @return iterable<string, array{non-empty-list<string>}>
     */
    public static function providerStringLiteralSegments(): iterable
    {
        for ($seed = 0; $seed < 100; $seed++) {
            $choices = new Choices('literal:' . $seed);
            $segments = [''];
            for ($index = 0, $length = $choices->integer(0, 80); $index < $length; $index++) {
                $position = $choices->integer(0, count($segments));
                $segments[$position] = ($segments[$position] ?? '') . $choices->one(['a', '\\', "'", '"', "\x00", "\n", "\r", "\x1a", '%', '_', '日本語']);
            }
            yield (string) $seed => [array_values($segments)];
        }
    }

    /**
     * The same structure executes to the same decoded bytes in both modes.
     *
     * @param non-empty-list<string> $segments
     */
    #[DataProvider('providerStringLiteralSegments')]
    #[Test]
    public function satisfiesDecodedBytesSurviveBothEscapeModes(array $segments): void
    {
        $literal = new Model($segments);
        foreach (['', 'NO_BACKSLASH_ESCAPES'] as $mode) {
            $session1 = (new Instance('8.4.7'))->connect();
            $session1->variables->session['sql_mode'] = $mode;
            $statement1 = (new Embedding())->statement($literal);
            $session1->activity->begin();
            StatementCounters::received($session1);
            $session1->hinted = [];
            $session1->dots = [];
            $operation1 = (new Binding())->operation($session1, $statement1, [], new LanguageProfile($session1->semantics()->profile()->grammar, $session1->semantics()->profile()->lexical, (new Bindings())->parameters($statement1)));
            $reply = (new Execution($session1))->perform($operation1, (new Dispatcher())->command($operation1->statement), []);
            self::assertInstanceOf(ResultSet::class, $reply);
            self::assertSame([[implode('', $segments)]], $reply->rows);
        }
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function providerStringLiteralNulNames(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            for ($seed = 0; $seed < 40; $seed++) {
                yield $release . ':' . $seed => [$release, $seed];
            }
        }
    }

    /**
     * Resolves a generated literal or explicit alias through its derived table's semantic name.
     */
    #[DataProvider('providerStringLiteralNulNames')]
    #[Test]
    public function satisfiesNulTerminatesTheColumnNameWithoutTruncatingTheValue(string $release, int $seed): void
    {
        $choices = new Choices('nul-column-name:' . $seed);
        $prefix = 'n' . str_repeat($choices->one(['a', 'x', '_']), $choices->integer(1, 30));
        $value = $prefix . "\x00" . str_repeat($choices->one(['b', '猫', "\x00"]), $choices->integer(1, 10));
        $alias = $choices->one([true, false]) ? new Name($value) : null;
        $inner = new Select([], [new SelectExpression(new Model([$value]), $alias)]);
        $outer = new Select([], [new SelectExpression(new ColumnUse(new Name($prefix)))], new DerivedTable($inner, new Name('input')));
        $session2 = (new Instance($release))->connect();
        $statement2 = (new Embedding())->statement($outer);
        $session2->activity->begin();
        StatementCounters::received($session2);
        $session2->hinted = [];
        $session2->dots = [];
        $operation2 = (new Binding())->operation($session2, $statement2, [], new LanguageProfile($session2->semantics()->profile()->grammar, $session2->semantics()->profile()->lexical, (new Bindings())->parameters($statement2)));
        $reply = (new Execution($session2))->perform($operation2, (new Dispatcher())->command($operation2->statement), []);
        self::assertInstanceOf(ResultSet::class, $reply);
        self::assertSame([[$value]], $reply->rows);
        self::assertSame($prefix, $reply->columns[0]->name);
    }
}
