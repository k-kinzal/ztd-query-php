<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Query\Optimization;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Query\Optimization\MaxExecutionTime;
use SqlSemantics\Model\Query\Optimization\OptimizerHint;
use SqlSemantics\SchemaBuilder;

#[CoversClass(OptimizerHint::class)]
#[Medium]
final class OptimizerHintTest extends TestCase
{
    public function testClassifiesAPlanningDirectiveSeparatelyFromComments(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SELECT /* note */ /*+ MAX_EXECUTION_TIME(1000) */ 1');
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertCount(1, $statement->hints);
        self::assertInstanceOf(MaxExecutionTime::class, $statement->hints[0]);
        self::assertSame('1000', $statement->hints[0]->milliseconds);
        self::assertSame('SELECT /*+ MAX_EXECUTION_TIME(1000) */ 1', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testIsAbsentWhenTheDialectHasNoHintSyntax(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('SELECT /*+ MAX_EXECUTION_TIME(1000) */ 1');
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertSame([], $statement->hints);
    }
}
