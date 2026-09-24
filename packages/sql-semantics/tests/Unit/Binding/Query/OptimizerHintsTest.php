<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Query\OptimizerHints;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;

#[CoversClass(OptimizerHints::class)]
#[Medium]
final class OptimizerHintsTest extends TestCase
{
    public function testBindReadsEveryDirectiveFromTheHintComments(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)')))->bind('SELECT /*+ MAX_EXECUTION_TIME(1000) MAX_EXECUTION_TIME(2) */ /*+ MAX_EXECUTION_TIME(3) */ a FROM t');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $statement);
        self::assertCount(3, $statement->hints);
        self::assertContainsOnlyInstancesOf(\SqlSemantics\Model\Query\Optimization\MaxExecutionTime::class, $statement->hints);
        self::assertSame(['1000', '2', '3'], array_column($statement->hints, 'milliseconds'));
        self::assertSame('SELECT /*+ MAX_EXECUTION_TIME(1000) MAX_EXECUTION_TIME(2) MAX_EXECUTION_TIME(3) */ `a` AS `a` FROM `t`', $statement->toString());
    }

    public function testBindIgnoresOrdinaryCommentsAndSelectsWithoutHints(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(a INT)'));
        $plain = $binder->bind('SELECT /* MAX_EXECUTION_TIME(1) */ a FROM t');
        self::assertInstanceOf(\SqlSemantics\Model\BoundSelect::class, $plain);
        self::assertSame([], $plain->hints);
        self::assertSame([], OptimizerHints::bind($binder->bind('SELECT a FROM t')->origin->source));
    }
}
