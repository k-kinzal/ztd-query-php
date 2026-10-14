<?php

declare(strict_types=1);

namespace Tests\Property\Structure\Program;

use Fuzz\Structure\Choices;
use Fuzz\Structure\Execution;
use MySqlMemory\Instance;
use MySqlMemory\Result\Batch;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
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
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateTrigger;
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

/**
 * Stored routines execute and expose metadata using directly constructed declarations.
 */
#[CoversNothing]
#[Medium]
final class CreationTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerValues(): iterable
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
    #[DataProvider('providerValues')]
    public function testProcedureCreationAndInvocationNeedNoSourceText(string $release, string $value): void
    {
        $session = (new Instance($release, [], ['d']))->connect('root', 'localhost', 'd');
        $session->text = 'unrelated invalid input';
        $execution = new Execution();
        $statement = new CreateProcedure(new QualifiedName(new Name('p')), new ParameterList([]), new QueryRoot(new Select([], [new SelectExpression(new NumberLiteral($value))])));

        $created = $execution->runIn($session, $statement);
        $called = $execution->runIn($session, new ProcedureCall(new QualifiedName(new Name('p'))));

        self::assertInstanceOf(Completion::class, $created);
        self::assertInstanceOf(Batch::class, $called);
        self::assertInstanceOf(ResultSet::class, $called->replies[0]);
        self::assertSame([[$value]], $called->replies[0]->rows);
        self::assertSame('SELECT ' . $value, $session->instance->dictionary->schema('d')?->procedures['p']->body);
    }

    /**
     * Parameter and return metadata bind a directly constructed function in the current database.
     */
    #[DataProvider('providerValues')]
    public function testFunctionCreationAndParameterBindingNeedNoSourceText(string $release, string $value): void
    {
        $session = (new Instance($release, [], ['d']))->connect('root', 'localhost', 'd');
        $session->text = 'unrelated invalid input';
        $execution = new Execution();
        $statement = new CreateFunction(new QualifiedName(new Name('f')), new ParameterList([new Parameter(new Name('a'), new Integral(IntegralKind::Int))]), new Integral(IntegralKind::Int), new ReturnStatement(new ColumnUse(new Name('a'))), characteristics: [new Determinism(true)]);

        $created = $execution->runIn($session, $statement);
        $called = $execution->runIn($session, new FunctionCall(new Name('f'), [new CallArgument(new NumberLiteral($value))]));

        self::assertInstanceOf(Completion::class, $created);
        self::assertInstanceOf(ResultSet::class, $called);
        self::assertSame([[$value]], $called->rows);
        self::assertSame('RETURN a', $session->instance->dictionary->schema('d')?->functions['f']->body);
        self::assertSame('a INT', $session->instance->dictionary->schema('d')->functions['f']->parameters);
    }

    /**
     * A constructed trigger runs after a constructed INSERT without reading source SQL.
     */
    #[DataProvider('providerValues')]
    public function testTriggerCreationAndActivationNeedNoSourceText(string $release, string $value): void
    {
        $session = (new Instance($release, [], ['d']))->connect('root', 'localhost', 'd');
        $session->text = 'unrelated invalid input';
        $execution = new Execution();
        $execution->runIn($session, new CreateTable(new QualifiedName(new Name('t')), [new ColumnDefinition(new ColumnName(new Name('a')), new OrdinaryColumn(new Integral(IntegralKind::Int)))]));
        $body = new SetVariables([new UserAssignment(new UserVariable(new Name('seen')), new NumberLiteral($value))]);
        $statement = new CreateTrigger(new QualifiedName(new Name('tr')), TriggerTime::Before, TriggerEvent::Insert, new TriggerTable(new QualifiedName(new Name('t'))), $body);

        $created = $execution->runIn($session, $statement);
        $inserted = $execution->runIn($session, new InsertRows(new InsertInto(false, null, false, new WriteTarget(new QualifiedName(new Name('t')))), [new InsertedRow([new NumberLiteral('1')])]));

        self::assertInstanceOf(Completion::class, $created);
        self::assertInstanceOf(Completion::class, $inserted);
        self::assertSame((int) $value, $session->variables->user('seen')[0]);
        self::assertSame('SET @seen = ' . $value, $session->instance->dictionary->schema('d')?->triggers[0]->body);
    }

    /**
     * Event metadata can be created and replaced using constructed bodies.
     */
    #[DataProvider('providerValues')]
    public function testEventCreationAndAlterationNeedNoSourceText(string $release, string $value): void
    {
        $session = (new Instance($release, [], ['d']))->connect('root', 'localhost', 'd');
        $session->text = 'unrelated invalid input';
        $execution = new Execution();
        $body = new SetVariables([new UserAssignment(new UserVariable(new Name('seen')), new NumberLiteral($value))]);
        $statement = new CreateEvent(new QualifiedName(new Name('e')), new RecurringSchedule(new NumberLiteral('1'), IntervalUnit::Day), $body, status: EventStatus::Disable);

        $created = $execution->runIn($session, $statement);
        $before = $session->instance->dictionary->schema('d')?->events['e']->body;
        $altered = $execution->runIn($session, new AlterEvent(new QualifiedName(new Name('e')), body: new SetVariables([new UserAssignment(new UserVariable(new Name('seen')), new NumberLiteral('0'))])));

        self::assertInstanceOf(Completion::class, $created);
        self::assertInstanceOf(Completion::class, $altered);
        self::assertSame('SET @seen = ' . $value, $before);
        self::assertSame('SET @seen = 0', $session->instance->dictionary->schema('d')->events['e']->body);
    }
}
