<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Optimization;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Model\Query\Optimization\MaxExecutionTime::class)]
final class MaxExecutionTimeTest extends TestCase
{
    public function testRetainsTheDeadlineWhenAProjectionChanges(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind('SELECT /*+ MAX_EXECUTION_TIME(1000) */ 1');
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Model\Query\Optimization\MaxExecutionTime::class, $statement->hints[0]);
        self::assertSame('1000', $statement->hints[0]->milliseconds);
        $changed = $statement->replaceExpression($statement->outputs[0]->expression, \SqlSemantics\Model\Expression::literal(2, Dialect::MySql));
        self::assertSame('SELECT /*+ MAX_EXECUTION_TIME(1000) */ 2', $changed->toString());
        self::assertSame('1', $statement->outputs[0]->expression->spelling());
    }

    public function testRequiresAnUnsignedIntegerDuration(): void
    {
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        new \SqlSemantics\Model\Query\Optimization\MaxExecutionTime('-1');
    }
}
