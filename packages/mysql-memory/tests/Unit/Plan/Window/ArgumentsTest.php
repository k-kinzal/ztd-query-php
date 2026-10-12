<?php

declare(strict_types=1);

namespace Tests\Unit\Plan\Window;

use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Leaf\Assignment;
use MySqlMemory\Evaluation\Leaf\ColumnRead;
use MySqlMemory\Evaluation\Leaf\Retyped;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Evaluation\Window\InputRead;
use MySqlMemory\Instance;
use MySqlMemory\Plan\Planner;
use MySqlMemory\Plan\Window\Arguments;
use MySqlMemory\Plan\Window\Functions;
use MySqlMemory\Typing\Domain;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunction;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Query\SelectExpression;

#[CoversClass(Arguments::class)]
#[Small]
final class ArgumentsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int}>
     */
    public static function providerArguments(): iterable
    {
        yield 'assignment' => ['FIRST_VALUE(@x:=@x+1)', 1];
        yield 'warning' => ['FIRST_VALUE(1/0)', 0];
    }

    #[DataProvider('providerArguments')]
    public function testSplitStoresAssignmentsAndLeavesWarningExpressionsForWindowReads(string $function, int $count): void
    {
        $session = (new Instance())->connect();
        $session->query('SET @x=0');
        $operation = $session->analyze('SELECT ' . $function . ' OVER ()');
        self::assertInstanceOf(Select::class, $operation->statement);
        $item = $operation->statement->items[0];
        self::assertInstanceOf(SelectExpression::class, $item);
        self::assertInstanceOf(WindowFunction::class, $item->expression);
        $planner = new Planner($operation->statement, $operation->facts, $session->settings(), new Connection($session->variables, new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)), $session->instance->dictionary);
        $analytic = (new Functions($planner))->compile($item->expression, new Scope());
        [$reads, $stored] = (new Arguments($planner))->split($analytic, 2, $item->expression);

        self::assertCount($count, $stored);
        self::assertCount(1, $reads);
    }

    public function testStoredRecognizesVariableEffectsInTheSemanticTree(): void
    {
        $session = (new Instance())->connect();
        $operation = $session->analyze('SELECT @x:=1, 1/0');
        self::assertInstanceOf(Select::class, $operation->statement);
        $planner = new Planner($operation->statement, $operation->facts, $session->settings(), new Connection($session->variables, new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)), $session->instance->dictionary);
        $arguments = new Arguments($planner);
        $assignment = $operation->statement->items[0];
        $division = $operation->statement->items[1];
        self::assertInstanceOf(SelectExpression::class, $assignment);
        self::assertInstanceOf(SelectExpression::class, $division);
        self::assertTrue($arguments->stored($assignment->expression));
        self::assertFalse($arguments->stored($division->expression));
    }

    /**
     * @return iterable<string, array{string, class-string<Evaluable>}>
     */
    public static function providerReleases(): iterable
    {
        yield 'current input' => ['8.0.44', InputRead::class];
        yield 'stored' => ['8.4.7', ColumnRead::class];
    }

    /**
     * @param class-string<Evaluable> $type
     */
    #[DataProvider('providerReleases')]
    public function testReadPreservesTheReleaseSpecificReferenceOfABareAssignment(string $version, string $type): void
    {
        $session = (new Instance($version))->connect();
        $operation = $session->analyze('SELECT 1');
        self::assertInstanceOf(Select::class, $operation->statement);
        $planner = new Planner($operation->statement, $operation->facts, $session->settings(), new Connection($session->variables, new Context($session->modes(), $session->diagnostics, $session->variables, 0.0)), $session->instance->dictionary);
        $assignment = new Retyped(new Assignment('x', new ColumnRead(Domain::integer(), 0), Domain::integer(), Domain::integer()), Domain::integer());

        self::assertInstanceOf($type, (new Arguments($planner))->read($assignment, 3));
    }
}
