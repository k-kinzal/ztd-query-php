<?php

declare(strict_types=1);

namespace Tests\Property\MySql\Statement\Routine\Program\Write\Index;

use JsonException;
use LogicException;
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
use SqlSemantics\Platform\MySql\Statement\Alter\Command\AddConstraint;
use SqlSemantics\Platform\MySql\Statement\Dml\ProcedureCall;
use SqlSemantics\Platform\MySql\Statement\Expression\Row;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnName;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\ParameterList;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\ConditionalBranch;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\IfStatement;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredAlterTable;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredCreateTable;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\Index\DeferredCreateIndex as Model;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Column\OrdinaryColumn;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ColumnPart;
use SqlSemantics\Platform\MySql\Statement\Table\Key\Deferred\ExpressionPart as DeferredExpressionPart;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ExpressionPart;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind;
use SqlSemantics\Platform\MySql\Statement\Table\Option\EngineOption;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
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
final class DeferredCreateIndexProperties extends TestCase
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
     * @return iterable<string, array{string, int, 'index'|'alter'|'table'}>
     */
    public static function providerSpatialKeyArityHistories(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            for ($seed = 0; $seed < 20; $seed++) {
                foreach (['index', 'alter', 'table'] as $form) {
                    yield $release . ':' . $seed . ':' . $form => [$release, $seed, $form];
                }
            }
        }
    }

    /**
     * Uses the same typed declaration and invocation without parsing SQL at any phase.
     *
     * @param 'index'|'alter'|'table' $form The retained definition role
     */
    #[DataProvider('providerSpatialKeyArityHistories')]
    #[Test]
    public function satisfiesRetainedIndexInstructionsPreserveSkippedAndReachedStates(string $release, int $seed, string $form): void
    {
        $choices = new Choices('spatial-key-arity:' . $seed);
        $name = new Name('spatial_key');
        $parts = array_map(static fn (int $number): ColumnPart => new ColumnPart(new Name($seed % 2 === 0 ? 'a' : 'c' . $number)), range(1, $choices->integer(2, 5)));
        $index = new IndexDefinition(IndexKind::Spatial, $parts, new ColumnName($name));
        $columns = array_map(static fn (string $name): ColumnDefinition => new ColumnDefinition(new ColumnName(new Name($name)), new OrdinaryColumn(new Integral(IntegralKind::Int))), array_values(array_unique(array_map(static fn (ColumnPart $part): string => $part->column->value, $parts))));
        $body = match ($form) {
            'index' => new Model($name, new QualifiedName(new Name('t')), $parts, IndexKind::Spatial),
            'alter' => new DeferredAlterTable(new QualifiedName(new Name('t')), [new AddConstraint($index)]),
            'table' => new DeferredCreateTable(new QualifiedName(new Name('fresh')), [...$columns, $index], [new EngineOption(new Name('MyISAM'))]),
        };
        $branch = new ConditionalBranch(new UserVariable(new Name('run')), [$body]);
        $create = new CreateProcedure(new QualifiedName(new Name('p')), new ParameterList([]), new IfStatement([$branch]));
        $call = new ProcedureCall(new QualifiedName(new Name('p')));
        $session = (new Instance($release, [], ['d']))->connect(database: 'd');
        $statement1 = (new Embedding())->statement(new CreateTable(new QualifiedName(new Name('t')), $columns, [new EngineOption(new Name('MyISAM'))]));
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
        $warnings1 = array_map(static fn (array $condition): array => [$condition[0], (string) $condition[1], $condition[2]], $session->diagnostics->conditions);
        self::assertNotInstanceOf(SqlError::class, $result1);
        $session->text = 'unrelated invalid input';
        $statement2 = (new Embedding())->statement($create);
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
        self::assertNotInstanceOf(SqlError::class, $result2);
        $error = $release === '5.6.51' ? [1210, 'HY000', 'Incorrect arguments to SPATIAL INDEX'] : [1070, '42000', 'Too many key parts specified; max 1 parts allowed'];
        $warning = ['Error', (string) $error[0], $error[2]];
        foreach ([0, 1, 0] as $phase => $run) {
            $statement3 = (new Embedding())->statement(new SetVariables([new UserAssignment(new UserVariable(new Name('run')), new NumberLiteral((string) $run))]));
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
            self::assertNotInstanceOf(SqlError::class, $result3);
            $statement4 = (new Embedding())->statement($call);
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
            $warnings4 = array_map(static fn (array $condition): array => [$condition[0], (string) $condition[1], $condition[2]], $session->diagnostics->conditions);
            $outcome = $result4;
            if ($run === 0) {
                self::assertNotInstanceOf(SqlError::class, $outcome);
                self::assertSame($release === '5.6.51' && $phase === 2 ? [$warning] : [], $warnings4);
            } else {
                self::assertSame($error, $outcome instanceof SqlError ? [$outcome->getCode(), $outcome->sqlState(), $outcome->getMessage()] : null);
                self::assertSame([$warning], $warnings4);
            }
        }
        self::assertSame('unrelated invalid input', $session->text);
        self::assertInstanceOf(IfStatement::class, $create->body);
        self::assertSame($body, $create->body->branches[0]->statements[0]);
    }

    /**
     * @return iterable<string, array{string, int, 'index'|'alter'|'table', IndexKind}>
     */
    public static function providerFunctionalIndexKindHistories(): iterable
    {
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            for ($seed = 0; $seed < 20; $seed++) {
                foreach (['index', 'alter', 'table'] as $form) {
                    foreach ([IndexKind::Primary, IndexKind::FullText, IndexKind::Spatial] as $kind) {
                        if ($form !== 'index' || $kind !== IndexKind::Primary) {
                            yield $release . ':' . $seed . ':' . $form . ':' . $kind->value => [$release, $seed, $form, $kind];
                        }
                    }
                }
            }
        }
    }

    /**
     * Uses the same typed declaration and invocation without parsing SQL at any phase.
     *
     * @param 'index'|'alter'|'table' $form The retained definition role
     * @throws LogicException When the provider supplies an unconstrained index kind
     */
    #[DataProvider('providerFunctionalIndexKindHistories')]
    #[Test]
    public function satisfiesRetainedIndexInstructionsPreserveSkippedAndReachedStatesFunctionalIndexKind(string $release, int $seed, string $form, IndexKind $kind): void
    {
        $choices = new Choices('functional-kind:' . $seed);
        $name = new Name('i');
        $part = new ExpressionPart(new NumberLiteral((string) $choices->integer(0, 100)));
        $index = new IndexDefinition($kind, [$part], new ColumnName($name));
        $column = new ColumnDefinition(new ColumnName(new Name('a')), new OrdinaryColumn(new Integral(IntegralKind::Int)));
        $body = match ($form) {
            'index' => new Model($name, new QualifiedName(new Name('t')), [$part], $kind),
            'alter' => new DeferredAlterTable(new QualifiedName(new Name('t')), [new AddConstraint($index)]),
            'table' => new DeferredCreateTable(new QualifiedName(new Name('fresh')), [$column, $index]),
        };
        $branch = new ConditionalBranch(new UserVariable(new Name('run')), [$body]);
        $create = new CreateProcedure(new QualifiedName(new Name('p')), new ParameterList([]), new IfStatement([$branch]));
        $call = new ProcedureCall(new QualifiedName(new Name('p')));
        $session = (new Instance($release, [], ['d']))->connect(database: 'd');
        $statement1 = (new Embedding())->statement(new CreateTable(new QualifiedName(new Name('t')), [$column]));
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
        $warnings1 = array_map(static fn (array $condition): array => [$condition[0], (string) $condition[1], $condition[2]], $session->diagnostics->conditions);
        self::assertNotInstanceOf(SqlError::class, $result1);
        $session->text = 'unrelated invalid input';
        $statement2 = (new Embedding())->statement($create);
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
        self::assertNotInstanceOf(SqlError::class, $result2);
        $error = match ($kind) {
            IndexKind::Primary => [3756, 'HY000', 'The primary key cannot be a functional index'],
            IndexKind::FullText => [3759, 'HY000', 'Fulltext functional index is not supported.'],
            IndexKind::Spatial => [3760, 'HY000', 'Spatial functional index is not supported.'],
            IndexKind::Index, IndexKind::Unique => throw new LogicException('A functional-kind property uses constrained kinds.'),
        };
        foreach ([0, 1, 0] as $run) {
            $statement3 = (new Embedding())->statement(new SetVariables([new UserAssignment(new UserVariable(new Name('run')), new NumberLiteral((string) $run))]));
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
            self::assertNotInstanceOf(SqlError::class, $result3);
            $statement4 = (new Embedding())->statement($call);
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
            $warnings4 = array_map(static fn (array $condition): array => [$condition[0], (string) $condition[1], $condition[2]], $session->diagnostics->conditions);
            $outcome = $result4;
            if ($run === 0) {
                self::assertNotInstanceOf(SqlError::class, $outcome);
                self::assertSame([], $warnings4);
            } else {
                self::assertSame($error, $outcome instanceof SqlError ? [$outcome->getCode(), $outcome->sqlState(), $outcome->getMessage()] : null);
                self::assertSame([['Error', (string) $error[0], $error[2]]], $warnings4);
            }
        }
        self::assertSame('unrelated invalid input', $session->text);
        self::assertInstanceOf(IfStatement::class, $create->body);
        self::assertSame($body, $create->body->branches[0]->statements[0]);
    }

    /**
     * @return iterable<string, array{string, int, 'index'|'alter'|'table'}>
     */
    public static function providerDeferredIndexHistories(): iterable
    {
        foreach (['8.0.44', '8.4.7', '9.1.0'] as $release) {
            for ($seed = 0; $seed < 20; $seed++) {
                foreach (['index', 'alter', 'table'] as $form) {
                    yield $release . ':' . $seed . ':' . $form => [$release, $seed, $form];
                }
            }
        }
    }

    /**
     * Uses the same typed declaration and invocation without parsing SQL at any phase.
     *
     * @param 'index'|'alter'|'table' $form The retained definition role
     */
    #[DataProvider('providerDeferredIndexHistories')]
    #[Test]
    public function satisfiesRetainedIndexInstructionsPreserveSkippedAndReachedStatesDeferredIndex(string $release, int $seed, string $form): void
    {
        $choices = new Choices('deferred-index:' . $seed);
        $values = array_map(static fn (): NumberLiteral => new NumberLiteral((string) $choices->integer(0, 1000)), range(1, $choices->integer(2, 4)));
        $part = new DeferredExpressionPart(new Row($values));
        $index = new IndexDefinition(IndexKind::Index, [$part], new ColumnName(new Name('i')));
        $column = new ColumnDefinition(new ColumnName(new Name('a')), new OrdinaryColumn(new Integral(IntegralKind::Int)));
        $body = match ($form) {
            'index' => new Model(new Name('i'), new QualifiedName(new Name('t')), [$part]),
            'alter' => new DeferredAlterTable(new QualifiedName(new Name('t')), [new AddConstraint($index)]),
            'table' => new DeferredCreateTable(new QualifiedName(new Name('fresh')), [$column, $index]),
        };
        $branch = new ConditionalBranch(new UserVariable(new Name('run')), [$body]);
        $create = new CreateProcedure(new QualifiedName(new Name('p')), new ParameterList([]), new IfStatement([$branch]));
        $call = new ProcedureCall(new QualifiedName(new Name('p')));
        $session = (new Instance($release, [], ['d']))->connect(database: 'd');
        $statement1 = (new Embedding())->statement(new CreateTable(new QualifiedName(new Name('t')), [$column]));
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
        $warnings1 = array_map(static fn (array $condition): array => [$condition[0], (string) $condition[1], $condition[2]], $session->diagnostics->conditions);
        self::assertNotInstanceOf(SqlError::class, $result1);
        $session->text = 'unrelated invalid input';
        $statement2 = (new Embedding())->statement($create);
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
        self::assertNotInstanceOf(SqlError::class, $result2);
        foreach ([0, 1, 0] as $run) {
            $statement3 = (new Embedding())->statement(new SetVariables([new UserAssignment(new UserVariable(new Name('run')), new NumberLiteral((string) $run))]));
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
            self::assertNotInstanceOf(SqlError::class, $result3);
            $statement4 = (new Embedding())->statement($call);
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
            $warnings4 = array_map(static fn (array $condition): array => [$condition[0], (string) $condition[1], $condition[2]], $session->diagnostics->conditions);
            $outcome = $result4;
            if ($run === 0) {
                self::assertNotInstanceOf(SqlError::class, $outcome);
                self::assertSame([], $warnings4);
            } else {
                self::assertSame([3800, 'HY000', "Expression of functional index 'i' cannot refer to a row value."], $outcome instanceof SqlError ? [$outcome->getCode(), $outcome->sqlState(), $outcome->getMessage()] : null);
                self::assertSame([['Error', '3800', "Expression of functional index 'i' cannot refer to a row value."]], $warnings4);
            }
        }
        self::assertSame('unrelated invalid input', $session->text);
        self::assertInstanceOf(IfStatement::class, $create->body);
        self::assertSame($body, $create->body->branches[0]->statements[0]);
    }

    /**
     * @return iterable<string, array{string, int, 'index'|'alter'|'table'}>
     */
    public static function providerIndexNameHistories(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            for ($seed = 0; $seed < 20; $seed++) {
                foreach (['index', 'alter', 'table'] as $form) {
                    yield $release . ':' . $seed . ':' . $form => [$release, $seed, $form];
                }
            }
        }
    }

    /**
     * Uses the same typed declaration and invocation without parsing SQL at any phase.
     *
     * @param 'index'|'alter'|'table' $form The retained definition role
     */
    #[DataProvider('providerIndexNameHistories')]
    #[Test]
    public function satisfiesRetainedIndexInstructionsPreserveSkippedAndReachedStatesIndexName(string $release, int $seed, string $form): void
    {
        $choices = new Choices('index-name:' . $seed);
        $name = new Name(['PRIMARY', 'primary', 'Primary', '', 'a '][$choices->integer(0, 4)]);
        $part = new ColumnPart(new Name('a'));
        $index = new IndexDefinition(IndexKind::Index, [$part], new ColumnName($name));
        $column = new ColumnDefinition(new ColumnName(new Name('a')), new OrdinaryColumn(new Integral(IntegralKind::Int)));
        $body = match ($form) {
            'index' => new Model($name, new QualifiedName(new Name('t')), [$part]),
            'alter' => new DeferredAlterTable(new QualifiedName(new Name('t')), [new AddConstraint($index)]),
            'table' => new DeferredCreateTable(new QualifiedName(new Name('fresh')), [$column, $index]),
        };
        $branch = new ConditionalBranch(new UserVariable(new Name('run')), [$body]);
        $create = new CreateProcedure(new QualifiedName(new Name('p')), new ParameterList([]), new IfStatement([$branch]));
        $call = new ProcedureCall(new QualifiedName(new Name('p')));
        $session = (new Instance($release, [], ['d']))->connect(database: 'd');
        $statement1 = (new Embedding())->statement(new CreateTable(new QualifiedName(new Name('t')), [$column]));
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
        $warnings1 = array_map(static fn (array $condition): array => [$condition[0], (string) $condition[1], $condition[2]], $session->diagnostics->conditions);
        self::assertNotInstanceOf(SqlError::class, $result1);
        $session->text = 'unrelated invalid input';
        $statement2 = (new Embedding())->statement($create);
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
        self::assertNotInstanceOf(SqlError::class, $result2);
        foreach ([0, 1, 0] as $phase => $run) {
            $statement3 = (new Embedding())->statement(new SetVariables([new UserAssignment(new UserVariable(new Name('run')), new NumberLiteral((string) $run))]));
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
            self::assertNotInstanceOf(SqlError::class, $result3);
            $statement4 = (new Embedding())->statement($call);
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
            $warnings4 = array_map(static fn (array $condition): array => [$condition[0], (string) $condition[1], $condition[2]], $session->diagnostics->conditions);
            $outcome = $result4;
            if ($run === 0) {
                self::assertNotInstanceOf(SqlError::class, $outcome);
                self::assertSame($release === '5.6.51' && $phase === 2 ? [['Error', '1280', "Incorrect index name '" . $name->value . "'"]] : [], $warnings4);
            } else {
                self::assertSame([1280, '42000', "Incorrect index name '" . $name->value . "'"], $outcome instanceof SqlError ? [$outcome->getCode(), $outcome->sqlState(), $outcome->getMessage()] : null);
                self::assertSame([['Error', '1280', "Incorrect index name '" . $name->value . "'"]], $warnings4);
            }
        }
        self::assertSame('unrelated invalid input', $session->text);
        self::assertInstanceOf(IfStatement::class, $create->body);
        self::assertSame($body, $create->body->branches[0]->statements[0]);
    }
}
