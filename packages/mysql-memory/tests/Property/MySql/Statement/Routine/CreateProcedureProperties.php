<?php

declare(strict_types=1);

namespace Tests\Property\MySql\Statement\Routine;

use JsonException;
use MySqlMemory\Command\Dispatcher;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Instance;
use MySqlMemory\Result\Batch;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\ResultSet;
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
use SqlSemantics\Platform\MySql\Statement\Call\CallArgument;
use SqlSemantics\Platform\MySql\Statement\Call\FunctionCall;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertedRow;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertInto;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows;
use SqlSemantics\Platform\MySql\Statement\Dml\ProcedureCall;
use SqlSemantics\Platform\MySql\Statement\Dml\WriteTarget;
use SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnName;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;
use SqlSemantics\Platform\MySql\Statement\QueryRoot;
use SqlSemantics\Platform\MySql\Statement\Routine\AlterEvent;
use SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\Determinism;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateEvent;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure as Model;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateTrigger;
use SqlSemantics\Platform\MySql\Statement\Routine\Definition\FunctionImplementation;
use SqlSemantics\Platform\MySql\Statement\Routine\Event\EventStatus;
use SqlSemantics\Platform\MySql\Statement\Routine\Event\RecurringSchedule;
use SqlSemantics\Platform\MySql\Statement\Routine\Parameter;
use SqlSemantics\Platform\MySql\Statement\Routine\ParameterList;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\ReturnStatement;
use SqlSemantics\Platform\MySql\Statement\Routine\Trigger\TriggerEvent;
use SqlSemantics\Platform\MySql\Statement\Routine\Trigger\TriggerTable;
use SqlSemantics\Platform\MySql\Statement\Routine\Trigger\TriggerTime;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Column\OrdinaryColumn;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables;
use SqlSemantics\Platform\MySql\Statement\Utility\Set\UserAssignment;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use Tests\Generator\Bindings;
use Tests\Generator\Catalogue;
use Tests\Generator\Choices;
use Tests\Generator\Construction;
use Tests\Generator\Embedding;
use Tests\Generator\Metadata;
use Tests\Generator\Profiles;

/**
 * Consumes generated analysis results through MemoryDB execution.
 */
#[CoversNothing]
#[Large]
final class CreateProcedureProperties extends TestCase
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
        $mode = (new Profiles())->mode($profile);
        $session = (new Instance(substr($profile->grammar->value, 6), ['sql_mode' => $mode], [$database]))->connect(database: $database);
        $parameters = array_fill(0, (new Bindings())->count($operation->statement), [null, Domain::null()]);
        try {
            $reply = (new Execution($session))->perform($operation, (new Dispatcher())->command($operation->statement), $parameters);
            self::assertGreaterThanOrEqual(0, $reply->warnings());
        } catch (SqlError $error) {
            self::assertTrue($error->signalled !== null || !in_array($error->error, [StatementError::UnknownError, StatementError::NotSupportedYet], true), self::MODEL . ': ' . $error->getMessage());
        }
    }
    /**
     * The semantic value sampled by this property.
     */
    public const MODEL = Model::class;
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerCreationValues(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7', '9.1.0'] as $release) {
            for ($seed = 0; $seed < 12; $seed++) {
                yield $release . ':' . $seed => [$release, (string) (new Choices('program-creation:' . $seed))->integer(0, 100000)];
            }
        }
    }

    /**
     * A generated procedure remains callable when no source SQL exists.
     */
    #[DataProvider('providerCreationValues')]
    #[Test]
    public function satisfiesProcedureCreationAndInvocationNeedNoSourceText(string $release, string $value): void
    {
        $session = (new Instance($release, [], ['d']))->connect('root', 'localhost', 'd');
        $session->text = 'unrelated invalid input';
        $statement = new Model(new QualifiedName(new Name('p')), new ParameterList([]), new QueryRoot(new Select([], [new SelectExpression(new NumberLiteral($value))])));
        $statement1 = (new Embedding())->statement($statement);
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        $operation1 = (new Binding())->operation($session, $statement1, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement1)));
        $created = (new Execution($session))->perform($operation1, (new Dispatcher())->command($operation1->statement), []);
        $statement2 = (new Embedding())->statement(new ProcedureCall(new QualifiedName(new Name('p'))));
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        $operation2 = (new Binding())->operation($session, $statement2, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement2)));
        $called = (new Execution($session))->perform($operation2, (new Dispatcher())->command($operation2->statement), []);
        self::assertInstanceOf(Completion::class, $created);
        self::assertInstanceOf(Batch::class, $called);
        self::assertInstanceOf(ResultSet::class, $called->replies[0]);
        self::assertSame([[$value]], $called->replies[0]->rows);
        self::assertSame('SELECT ' . $value, $session->instance->dictionary->schema('d')?->procedures['p']->body);
    }

    /**
     * \SqlSemantics\Platform\MySql\Statement\Routine\Parameter and return metadata bind a directly constructed function in the current database.
     */
    #[DataProvider('providerCreationValues')]
    #[Test]
    public function satisfiesFunctionCreationAndParameterBindingNeedNoSourceText(string $release, string $value): void
    {
        $session = (new Instance($release, [], ['d']))->connect('root', 'localhost', 'd');
        $session->text = 'unrelated invalid input';
        $statement = new CreateFunction(new QualifiedName(new Name('f')), new ParameterList([new Parameter(new Name('a'), new Integral(IntegralKind::Int))]), new Integral(IntegralKind::Int), new FunctionImplementation(new ReturnStatement(new ColumnUse(new Name('a'))), [new Determinism(true)]));
        $statement3 = (new Embedding())->statement($statement);
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        $operation3 = (new Binding())->operation($session, $statement3, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement3)));
        $created = (new Execution($session))->perform($operation3, (new Dispatcher())->command($operation3->statement), []);
        $statement4 = (new Embedding())->statement(new FunctionCall(new Name('f'), [new CallArgument(new NumberLiteral($value))]));
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        $operation4 = (new Binding())->operation($session, $statement4, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement4)));
        $called = (new Execution($session))->perform($operation4, (new Dispatcher())->command($operation4->statement), []);
        self::assertInstanceOf(Completion::class, $created);
        self::assertInstanceOf(ResultSet::class, $called);
        self::assertSame([[$value]], $called->rows);
        self::assertSame('RETURN a', $session->instance->dictionary->schema('d')?->functions['f']->body);
        self::assertSame('a INT', $session->instance->dictionary->schema('d')->functions['f']->parameters);
    }

    /**
     * A constructed trigger runs after a constructed INSERT without reading source SQL.
     */
    #[DataProvider('providerCreationValues')]
    #[Test]
    public function satisfiesTriggerCreationAndActivationNeedNoSourceText(string $release, string $value): void
    {
        $session = (new Instance($release, [], ['d']))->connect('root', 'localhost', 'd');
        $session->text = 'unrelated invalid input';
        $statement5 = (new Embedding())->statement(new CreateTable(new QualifiedName(new Name('t')), [new ColumnDefinition(new ColumnName(new Name('a')), new OrdinaryColumn(new Integral(IntegralKind::Int)))]));
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        $operation5 = (new Binding())->operation($session, $statement5, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement5)));
        (new Execution($session))->perform($operation5, (new Dispatcher())->command($operation5->statement), []);
        $body = new SetVariables([new UserAssignment(new UserVariable(new Name('seen')), new NumberLiteral($value))]);
        $statement = new CreateTrigger(new QualifiedName(new Name('tr')), TriggerTime::Before, TriggerEvent::Insert, new TriggerTable(new QualifiedName(new Name('t'))), $body);
        $statement6 = (new Embedding())->statement($statement);
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        $operation6 = (new Binding())->operation($session, $statement6, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement6)));
        $created = (new Execution($session))->perform($operation6, (new Dispatcher())->command($operation6->statement), []);
        $statement7 = (new Embedding())->statement(new InsertRows(new InsertInto(false, null, false, new WriteTarget(new QualifiedName(new Name('t')))), [new InsertedRow([new NumberLiteral('1')])]));
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        $operation7 = (new Binding())->operation($session, $statement7, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement7)));
        $inserted = (new Execution($session))->perform($operation7, (new Dispatcher())->command($operation7->statement), []);
        self::assertInstanceOf(Completion::class, $created);
        self::assertInstanceOf(Completion::class, $inserted);
        self::assertSame((int) $value, $session->variables->user('seen')[0]);
        self::assertSame('SET @seen = ' . $value, $session->instance->dictionary->schema('d')?->triggers[0]->body);
    }

    /**
     * Event metadata can be created and replaced using constructed bodies.
     */
    #[DataProvider('providerCreationValues')]
    #[Test]
    public function satisfiesEventCreationAndAlterationNeedNoSourceText(string $release, string $value): void
    {
        $session = (new Instance($release, [], ['d']))->connect('root', 'localhost', 'd');
        $session->text = 'unrelated invalid input';
        $body = new SetVariables([new UserAssignment(new UserVariable(new Name('seen')), new NumberLiteral($value))]);
        $statement = new CreateEvent(new QualifiedName(new Name('e')), new RecurringSchedule(new NumberLiteral('1'), IntervalUnit::Day), $body, status: EventStatus::Disable);
        $statement8 = (new Embedding())->statement($statement);
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        $operation8 = (new Binding())->operation($session, $statement8, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement8)));
        $created = (new Execution($session))->perform($operation8, (new Dispatcher())->command($operation8->statement), []);
        $before = $session->instance->dictionary->schema('d')?->events['e']->body;
        $statement9 = (new Embedding())->statement(new AlterEvent(new QualifiedName(new Name('e')), body: new SetVariables([new UserAssignment(new UserVariable(new Name('seen')), new NumberLiteral('0'))])));
        $session->activity->begin();
        StatementCounters::received($session);
        $session->hinted = [];
        $session->dots = [];
        $operation9 = (new Binding())->operation($session, $statement9, [], new LanguageProfile($session->semantics()->profile()->grammar, $session->semantics()->profile()->lexical, (new Bindings())->parameters($statement9)));
        $altered = (new Execution($session))->perform($operation9, (new Dispatcher())->command($operation9->statement), []);
        self::assertInstanceOf(Completion::class, $created);
        self::assertInstanceOf(Completion::class, $altered);
        self::assertSame('SET @seen = ' . $value, $before);
        self::assertSame('SET @seen = 0', $session->instance->dictionary->schema('d')?->events['e']->body);
    }

    /**
     * @throws JsonException When a semantic inventory is invalid
     */
    #[Test]
    public function satisfiesPreservesTheGeneratedProcedureAfterRejectingIncompatibleReleaseOperands(): void
    {
        $catalogue = new Catalogue();
        $choices = new Choices('semantic-types-1607:1099');
        $choices->one($catalogue->roots());
        $construction = new Construction($choices, $catalogue, new Metadata());
        $node = $construction->object(Model::class, 6);
        self::assertInstanceOf(Model::class, $node);
        self::assertSame('mysql', (new Profiles())->select($node)->grammar->database());
        self::assertArrayHasKey(Model::class . ': A query cannot combine features removed before MySQL 8.0 with features introduced in MySQL 8.0 or later.', $construction->rejections);
        $subject = $node;
        $profile = (new Profiles())->select($subject);
        $session = (new Instance(substr($profile->grammar->value, 6)))->connect();
        $session->variables->session['sql_mode'] = (new Profiles())->mode($profile);
        $statement = (new Embedding())->statement($subject);
        $parameters = array_fill(0, (new Bindings())->count($statement), [null, Domain::null()]);
        try {
            $operation = (new Binding())->operation($session, $statement, $parameters, $profile);
            $reply = (new Execution($session))->perform($operation, (new Dispatcher())->command($operation->statement), $parameters);
            self::assertGreaterThanOrEqual(0, $reply->warnings());
        } catch (SqlError $failure) {
            self::assertTrue($failure->signalled !== null || !in_array($failure->error, [StatementError::UnknownError, StatementError::NotSupportedYet], true), $failure->getMessage());
        }
    }
}
