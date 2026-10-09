<?php

declare(strict_types=1);

namespace Tests\Unit\Plan;

use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Instance;
use MySqlMemory\Plan\Equivalence;
use MySqlMemory\Plan\Planner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Reference\Column\ResolvedColumn;

#[CoversClass(Equivalence::class)]
#[Small]
final class EquivalenceTest extends TestCase
{
    public function testSameColumnTellsWhetherANameResolvesToTheColumnOfAResolution(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT, b INT)');
        $operation = $session->analyze('SELECT t.a, a, b FROM t');
        $statement = $operation->statement;
        self::assertInstanceOf(Select::class, $statement);
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $planner = new Planner($statement, $operation->facts, $session->settings(), new Connection($session->variables, $context), $session->instance->dictionary);
        $first = $operation->facts->scalar($operation->field(0)->expression ?? new ColumnUse(new Name('a')))->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $first);
        $second = $operation->field(1)->expression;
        $third = $operation->field(2)->expression;
        self::assertInstanceOf(ColumnUse::class, $second);
        self::assertInstanceOf(ColumnUse::class, $third);

        self::assertSame([true, false], [(new Equivalence($planner))->sameColumn($first, $second), (new Equivalence($planner))->sameColumn($first, $third)]);
    }

    public function testColumnAnswersTheSameColumnForEveryUseOfIt(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT)');
        $operation = $session->analyze('SELECT a FROM t GROUP BY a WITH ROLLUP');
        $statement = $operation->statement;
        self::assertInstanceOf(Select::class, $statement);
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $planner = new Planner($statement, $operation->facts, $session->settings(), new Connection($session->variables, $context), $session->instance->dictionary);
        $item = $operation->facts->scalar($operation->field(0)->expression ?? new ColumnUse(new Name('a')))->resolution;
        $group = $operation->facts->scalar($statement->groupBy->items[0]->expression ?? new ColumnUse(new Name('a')))->resolution;
        self::assertInstanceOf(ResolvedColumn::class, $item);
        self::assertInstanceOf(ResolvedColumn::class, $group);

        self::assertSame((new Equivalence($planner))->column($group), (new Equivalence($planner))->column($item));
    }

    public function testSameComparesExpressionsWithoutRegardToParenthesesCaseOrQualifiers(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT, b INT)');
        $operation = $session->analyze('SELECT ABS(t.a + 1), (abs(a + 1)), ABS(b + 1) FROM t');
        $statement = $operation->statement;
        self::assertInstanceOf(Select::class, $statement);
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $planner = new Planner($statement, $operation->facts, $session->settings(), new Connection($session->variables, $context), $session->instance->dictionary);
        $equivalence = new Equivalence($planner);

        self::assertSame([true, false, true], [$equivalence->same($operation->field(0)->expression, $operation->field(1)->expression), $equivalence->same($operation->field(0)->expression, $operation->field(2)->expression), $equivalence->same(1, 1)]);
    }

    public function testSamePropertiesComparesEachPropertyAsAnExpression(): void
    {
        $session = (new Instance())->connect();
        $session->query('CREATE DATABASE d');
        $session->query('USE d');
        $session->query('CREATE TABLE t (a INT, b INT)');
        $operation = $session->analyze('SELECT t.a + 1, (A + 1), a + 2 FROM t');
        $statement = $operation->statement;
        self::assertInstanceOf(Select::class, $statement);
        $context = new Context($session->modes(), $session->diagnostics, $session->variables, 0.0);
        $planner = new Planner($statement, $operation->facts, $session->settings(), new Connection($session->variables, $context), $session->instance->dictionary);
        $equivalence = new Equivalence($planner);
        $first = $operation->field(0)->expression;
        $second = $operation->field(1)->expression;
        $third = $operation->field(2)->expression;
        self::assertInstanceOf(Grouped::class, $second);
        self::assertNotNull($first);
        self::assertNotNull($third);

        self::assertSame([true, false], [$equivalence->sameProperties($first, $second->operand), $equivalence->sameProperties($first, $third)]);
    }
}
