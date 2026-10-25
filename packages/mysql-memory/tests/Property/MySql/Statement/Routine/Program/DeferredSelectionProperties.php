<?php

declare(strict_types=1);

namespace Tests\Property\MySql\Statement\Routine\Program;

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
use SqlSemantics\Platform\MySql\Statement\Dml\ProcedureCall;
use SqlSemantics\Platform\MySql\Statement\Expression\Row;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\RowLimit;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\Query\SelectOption;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\ParameterList;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredProjection;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredSelection as Model;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\ConditionalBranch;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\IfStatement;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredGrouping;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredOrderItem;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredQuery;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\UserAssignment;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Tests\Generator\Bindings;
use Tests\Generator\Choices;
use Tests\Generator\Embedding;

/**
 * Samples the public semantic value and checks its constructor contract.
 */
#[CoversNothing]
#[Large]
final class DeferredSelectionProperties extends TestCase
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
     * @throws JsonException When a dependency inventory cannot be read
     * Every sampled constructor accepts arguments within its declared invariant domain.
     * @param non-negative-int $sample
     */
    #[Test]
    #[DataProvider('providerSamples')]
    public function satisfiesPublicValueInvariants(int $sample): void
    {
        $value = (new \Tests\Generator\Samples())->sample(self::MODEL, $sample);
        self::assertInstanceOf(self::MODEL, $value);
    }
    /**
     * The semantic value sampled by this property.
     */
    public const MODEL = Model::class;
    /**
     * @return iterable<string, array{string, int<0, 19>, 'projection'|'where'|'group'|'having'|'order'}>
     */
    public static function providerDeferredSelectionClausesHistories(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            for ($seed = 0; $seed < 20; $seed++) {
                foreach (['projection', 'where', 'group', 'having', 'order'] as $form) {
                    yield $release . ':' . $seed . ':' . $form => [$release, $seed, $form];
                }
            }
        }
    }

    /**
     * Reuses one directly constructed invocation while changing only session history.
     *
     * @param int<0, 19> $seed The generated history index
     * @param 'projection'|'where'|'group'|'having'|'order' $form The retained expression role
     */
    #[DataProvider('providerDeferredSelectionClausesHistories')]
    #[Test]
    public function satisfiesUnchangedProgramStructurePreservesReachedAndSkippedOutcomes(string $release, int $seed, string $form): void
    {
        $choices = new Choices('deferred-selection-clauses:' . $seed);
        $width = $choices->integer(2, 4);
        $items = [];
        for ($index = 0; $index < $width; $index++) {
            $items[] = new NumberLiteral((string) $choices->integer(0, 1000));
        }
        $row = new Row($items);
        $ordinary = [new SelectExpression(new NumberLiteral('1'))];
        $source = new TableReference(new QualifiedName(new Name('fixture')));
        $order = new DeferredOrderItem($row);
        $body = match ($form) {
            'projection' => new Model([new DeferredProjection($row)], $source, options: [SelectOption::Distinct], limit: new RowLimit(new NumberLiteral('0'))),
            'where' => new Model($ordinary, $source, $row),
            'group' => new Model($ordinary, $source, groupBy: new DeferredGrouping([$order])),
            'having' => new Model($ordinary, $source, having: $row),
            'order' => new DeferredQuery(new Select([], $ordinary, $source, orderBy: [$order])),
        };
        $branch = new ConditionalBranch(new UserVariable(new Name('run')), [$body]);
        $create = new CreateProcedure(new QualifiedName(new Name('p')), new ParameterList([]), new IfStatement([$branch]));
        $call = new ProcedureCall(new QualifiedName(new Name('p')));
        $session = (new Instance($release, [], ['d']))->connect(database: 'd');
        $session->query('CREATE TABLE fixture(a INT); INSERT INTO fixture VALUES(1),(2)');
        $session->text = 'unrelated invalid input';
        $statement1 = (new Embedding())->statement($create);
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
        self::assertNotInstanceOf(SqlError::class, $result1);
        $conditions = [['Error', '1241', 'Operand should contain 1 column(s)']];
        foreach ([0, 1, 0] as $phase => $run) {
            $statement2 = (new Embedding())->statement(new SetVariables([new UserAssignment(new UserVariable(new Name('run')), new NumberLiteral((string) $run))]));
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
            self::assertNotInstanceOf(SqlError::class, $result2);
            $statement3 = (new Embedding())->statement($call);
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
            $outcome = $result3;
            if ($run === 0) {
                self::assertNotInstanceOf(SqlError::class, $outcome);
                self::assertSame($release === '5.6.51' && $phase === 2 ? $conditions : [], $warnings3);
            } else {
                self::assertSame([1241, '21000', 'Operand should contain 1 column(s)'], $outcome instanceof SqlError ? [$outcome->getCode(), $outcome->sqlState(), $outcome->getMessage()] : null);
                self::assertSame($conditions, $warnings3);
            }
        }
        self::assertSame('unrelated invalid input', $session->text);
    }
}
