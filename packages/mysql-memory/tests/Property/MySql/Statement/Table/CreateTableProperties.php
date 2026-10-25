<?php

declare(strict_types=1);

namespace Tests\Property\MySql\Statement\Table;

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
use SqlSemantics\Platform\MySql\Statement\Alter\AlterTable;
use SqlSemantics\Platform\MySql\Statement\Alter\Column\AddColumn;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\BinaryCast;
use SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\CharsetName;
use SqlSemantics\Platform\MySql\Statement\Name\CollationName;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnName;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Column\OrdinaryColumn;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable as Model;
use SqlSemantics\Platform\MySql\Statement\Table\Option\CharsetOption;
use SqlSemantics\Platform\MySql\Statement\Table\Option\CollationOption;
use SqlSemantics\Platform\MySql\Statement\Table\TableQuery;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Tests\Generator\Bindings;
use Tests\Generator\Choices;
use Tests\Generator\Embedding;

/**
 * Consumes generated analysis results through MemoryDB execution.
 */
#[CoversNothing]
#[Large]
final class CreateTableProperties extends TestCase
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
     * @return iterable<string, array{string, AlterTable}>
     */
    public static function providerDefinitionNamesHistories(): iterable
    {
        $choices = new Choices('definition-names-20261017');
        foreach (['5.6.51', '5.7.44'] as $release) {
            for ($trial = 0; $trial < 40; $trial++) {
                $column = new ColumnName(new Name('c' . $choices->integer(0, 10000)), new QualifiedName(new Name('t'), new Name('x')));
                yield $release . ':' . $trial => [$release, new AlterTable(new QualifiedName(new Name('t')), [new AddColumn(new ColumnDefinition($column, new OrdinaryColumn(new Integral(IntegralKind::Int))))])];
            }
        }
    }

    /**
     * The same name starts in another database, then a missing table, then populated and duplicate states.
     */
    #[DataProvider('providerDefinitionNamesHistories')]
    #[Test]
    public function satisfiesTheSameQualifiedDefinitionFollowsTheCurrentDatabase(string $release, AlterTable $statement): void
    {
        $session = (new Instance($release))->connect();
        $session->query('CREATE DATABASE d; CREATE DATABASE x; USE d');
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
        $other = $result1;
        $session->query('USE x');
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
        $missing = $result2;
        $session->query('CREATE TABLE t(a INT); INSERT INTO t VALUES(1),(2)');
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
        $success = $result3;
        $statement4 = (new Embedding())->statement($statement);
        $parameters4 = array_fill(0, (new Bindings())->count($statement4), [null, Domain::null()]);
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        try {
            $operation4 = (new Binding())->operation($session, $statement4, $parameters4, new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement4)));
            $result4 = (new Execution($session))->perform($operation4, (new Dispatcher())->command($operation4->statement), $parameters4);
        } catch (SqlError $executionError) {
            $result4 = $executionError;
            (new Execution($session))->failed($executionError);
        }
        $duplicate = $result4;
        self::assertSame(1102, ($other instanceof SqlError ? [$other->getCode(), $other->sqlState(), $other->getMessage()] : null)[0] ?? null);
        self::assertSame(1146, ($missing instanceof SqlError ? [$missing->getCode(), $missing->sqlState(), $missing->getMessage()] : null)[0] ?? null);
        self::assertNotInstanceOf(SqlError::class, $success);
        self::assertSame(1060, ($duplicate instanceof SqlError ? [$duplicate->getCode(), $duplicate->sqlState(), $duplicate->getMessage()] : null)[0] ?? null);
        self::assertSame('unrelated invalid input', $session->text);
    }

    /**
     * A dot prefix remains observable without any parsed input.
     */
    #[Test]
    public function satisfiesADirectDotPrefixedNameWarnsBeforeTheMissingTable(): void
    {
        $session = (new Instance('5.7.44'))->connect();
        $session->query('CREATE DATABASE d; USE d');
        $statement = new AlterTable(new QualifiedName(new Name('t')), [new AddColumn(new ColumnDefinition(new ColumnName(new Name('a'), dot: OptionalWords::Written), new OrdinaryColumn(new Integral(IntegralKind::Int))))]);
        $session->text = 'unrelated invalid input';
        $statement5 = (new Embedding())->statement($statement);
        $parameters5 = array_fill(0, (new Bindings())->count($statement5), [null, Domain::null()]);
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        try {
            $operation5 = (new Binding())->operation($session, $statement5, $parameters5, new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement5)));
            $result5 = (new Execution($session))->perform($operation5, (new Dispatcher())->command($operation5->statement), $parameters5);
        } catch (SqlError $executionError) {
            $result5 = $executionError;
            (new Execution($session))->failed($executionError);
        }
        $warnings5 = array_map(static fn (array $condition): array => [$condition[0], (string) $condition[1], $condition[2]], $session->diagnostics->conditions);
        $outcome = $result5;
        self::assertSame(1146, ($outcome instanceof SqlError ? [$outcome->getCode(), $outcome->sqlState(), $outcome->getMessage()] : null)[0] ?? null);
        self::assertArrayHasKey(0, $warnings5);
        self::assertSame('1287', $warnings5[0][1]);
        self::assertSame("'.<column>' is deprecated and will be removed in a future release. Please use the column name without a dot prefix instead", $warnings5[0][2]);
    }

    /**
     * @return iterable<string, array{string, bool, bool, int}>
     */
    public static function providerTableOptionsOptions(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            foreach ([false, true] as $selected) {
                foreach ([false, true] as $qualified) {
                    for ($seed = 0; $seed < 8; $seed++) {
                        yield $release . ':' . (int) $selected . ':' . (int) $qualified . ':' . $seed => [$release, $selected, $qualified, $seed];
                    }
                }
            }
        }
    }

    /**
     * Varies the target database and competing option failures before a query warning.
     */
    #[DataProvider('providerTableOptionsOptions')]
    #[Test]
    public function satisfiesTypedOptionOrderSurvivesDirectSubmission(string $release, bool $selected, bool $qualified, int $seed): void
    {
        $choices = new Choices('table-options:' . $seed);
        $name = new Name('missing_' . $choices->integer(0, 10000));
        $options = [new CharsetOption(new CharsetName($name)), new CollationOption(new CollationName($name))];
        $reverse = $seed % 2 === 1;
        $query = new TableQuery(new Select([], [new SelectExpression(new BinaryCast(new NumberLiteral('1')))]));
        $create = new Model(new QualifiedName(new Name('t'), $qualified ? new Name('d') : null), options: $reverse ? array_reverse($options) : $options, query: $query);
        $session = (new Instance($release, [], ['d']))->connect();
        $session->variables->database = $selected ? 'd' : '';
        $session->text = 'unrelated invalid SQL';
        $expected = !$selected && !$qualified && $session->settings()->legacy() ? 1046 : ($reverse ? 1273 : 1115);
        try {
            $statement1 = (new Embedding())->statement($create);
            $session->activity->begin();
            StatementCounters::received($session);
            $session->hinted = [];
            $session->dots = [];
            $operation1 = (new Binding())->operation($session, $statement1, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement1)));
            (new Execution($session))->perform($operation1, (new Dispatcher())->command($operation1->statement), []);
            self::fail('The first unknown option must retain its error.');
        } catch (SqlError $error) {
            self::assertSame($expected, $error->getCode());
            self::assertSame($expected === 1046 ? [] : [$expected], array_column($session->diagnostics->conditions, 1), 'The following query must not emit its BINARY warning.');
        }
    }
}
