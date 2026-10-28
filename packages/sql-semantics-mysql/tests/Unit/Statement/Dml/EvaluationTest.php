<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Dml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Dml\Evaluation;

#[CoversClass(Evaluation::class)]
#[Medium]
final class EvaluationTest extends TestCase
{
    public function testDeriveStatementDerivesTheItemsWithoutOutput(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('DO 1 + 1, @a := 2');
        self::assertInstanceOf(Evaluation::class, $operation);

        self::assertCount(2, $operation->items);
        self::assertNull($operation->output());
    }

    public function testRenderWritesTheItems(): void
    {
        self::assertSame('DO 1, 2', (new Semantics(Dialect::MySql, 'mysql-5.6.51'))->analyze('do 1, 2')->toString());
        self::assertSame('DO 1 x', (new Semantics(Dialect::MySql, 'mysql-5.7.44'))->analyze('do 1 x')->toString());
    }

    #[\PHPUnit\Framework\Attributes\TestWith(['DO 1', false])]
    #[\PHPUnit\Framework\Attributes\TestWith(['DO *', true])]
    #[\PHPUnit\Framework\Attributes\TestWith(['DO (1, 2)', true])]
    public function testHasExecutionConditionRetainsRequestsThatCannotPrepare(string $sql, bool $conditional): void
    {
        $semantics = new Semantics(Dialect::MySql);
        $statement = $semantics->analyze($sql);
        self::assertInstanceOf(Evaluation::class, $statement);
        self::assertSame($conditional, $statement->hasExecutionCondition());
    }

    public function testProblemRetainsDependenciesAfterTheFixedCondition(): void
    {
        $statement = (new Semantics(Dialect::MySql))->analyze('DO (1, 2), (SELECT a FROM missing)');
        self::assertInstanceOf(Evaluation::class, $statement);
        self::assertSame('Operand should contain 1 column(s), not 2.', $statement->problem()?->message());
        self::assertCount(2, $statement->items);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Query\SelectExpression::class, $statement->items[1]);
        self::assertSame('DO (1, 2), (SELECT a FROM missing)', $statement->toString());
        $wildcard = (new Semantics(Dialect::MySql))->analyze('DO t.*, (SELECT a FROM t)');
        self::assertInstanceOf(Evaluation::class, $wildcard);
        self::assertCount(2, $wildcard->items);
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Dml\Problem\EvaluationWildcard::class, $wildcard->problem());
        self::assertSame('DO t.*, (SELECT a FROM t)', $wildcard->toString());
    }
}
