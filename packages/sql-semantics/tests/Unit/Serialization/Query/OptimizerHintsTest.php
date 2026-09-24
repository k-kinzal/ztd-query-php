<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundSelect;
use SqlSemantics\Model\Query\Optimization\MaxExecutionTime;
use SqlSemantics\Model\Query\Optimization\OptimizerHint;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Query\OptimizerHints;

#[CoversClass(OptimizerHints::class)]
#[Medium]
final class OptimizerHintsTest extends TestCase
{
    public function testWriteIsEmptyWithoutDirectives(): void
    {
        self::assertSame('', OptimizerHints::write([])->toString());
    }

    public function testWriteSpellsMaxExecutionTimeAsAnOptimizerComment(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build('CREATE TABLE t(id INT)'));
        $statement = $binder->bind('SELECT /*+ MAX_EXECUTION_TIME(1000) */ id FROM t');
        self::assertInstanceOf(BoundSelect::class, $statement);
        self::assertInstanceOf(MaxExecutionTime::class, $statement->hints[0]);
        self::assertSame('1000', $statement->hints[0]->milliseconds);
        self::assertSame('/*+ MAX_EXECUTION_TIME(1000) */', OptimizerHints::write($statement->hints)->toString());
        self::assertSame('SELECT /*+ MAX_EXECUTION_TIME(1000) */ `id` AS `id` FROM `t`', $statement->toString());
        $rebound = $binder->bind($statement->toString());
        self::assertInstanceOf(BoundSelect::class, $rebound);
        self::assertInstanceOf(MaxExecutionTime::class, $rebound->hints[0]);
        self::assertSame('1000', $rebound->hints[0]->milliseconds);
    }

    public function testWriteJoinsSeveralDirectivesWithSpaces(): void
    {
        self::assertSame('/*+ MAX_EXECUTION_TIME(5) MAX_EXECUTION_TIME(7) */', OptimizerHints::write([new MaxExecutionTime('5'), new MaxExecutionTime('7')])->toString());
    }

    public function testWriteRejectsAnUnclassifiedDirective(): void
    {
        $this->expectException(InvalidStructure::class);
        OptimizerHints::write([new class () implements OptimizerHint {
        }]);
    }
}
