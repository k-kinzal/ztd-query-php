<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Partition\Method;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Platform\MySql\Statement\Partition\Method\DeferredMethod;

#[CoversClass(DeferredMethod::class)]
#[Medium]
final class DeferredMethodTest extends TestCase
{
    public function testRetainedSubqueryExcludesAModernNativeFunction(): void
    {
        $call = new M\Call\FunctionCall(new \SqlSemantics\Statement\Identifier\Name('ST_LATITUDE'), [new M\Call\CallArgument(new M\Literal\NumberLiteral('1'))]);
        $query = new M\Expression\Subquery\ScalarSubquery(new M\Query\Select([], [new M\Query\SelectExpression($call)]));
        $this->expectExceptionMessage('The retained operands must share a supported MySQL release.');
        new DeferredMethod($query);
    }

    public function testRetainedSubqueryExcludesAWithClause(): void
    {
        $definition = new M\Query\With\CommonTableExpression(new \SqlSemantics\Statement\Identifier\Name('q'), [], new M\Query\Select([], [new M\Query\SelectExpression(new M\Literal\NumberLiteral('1'))]));
        $query = new M\Query\QueryExpression(new M\Query\With\With(false, [$definition]), new M\Query\Select([], [new M\Query\SelectExpression(new M\Literal\NumberLiteral('2'))]));
        $operand = new M\Expression\Subquery\ScalarSubquery($query);
        $this->expectExceptionMessage('The retained operands must share a supported MySQL release.');
        new DeferredMethod($operand);
    }

    public function testDeriveMethodRetainsItsWholeOperandForContextualization(): void
    {
        $row = new M\Expression\Row([new M\Name\ColumnUse(new \SqlSemantics\Statement\Identifier\Name('absent')), new M\Literal\NumberLiteral('1')]);
        $method = new DeferredMethod($row);
        $derivation = new Derivation((new Semantics(Dialect::MySql))->context());
        $method->deriveMethod($derivation, $derivation->environment());
        $diagnostics = $derivation->facts()->diagnostics;
        $problem = $diagnostics[count($diagnostics) - 1];
        self::assertInstanceOf(\SqlSemantics\Statement\Reference\Column\MissingColumn::class, $problem);
        self::assertSame($row, $method->expression);
    }

    public function testRenderRetainsTheMethodInsideAStoredAlteration(): void
    {
        $operation = (new Semantics(Dialect::MySql))->analyze('CREATE PROCEDURE p() ALTER TABLE t ENGINE=InnoDB PARTITION BY LINEAR HASH((1,2)) PARTITIONS 3');
        self::assertInstanceOf(M\Statement::class, $operation);
        self::assertSame('CREATE PROCEDURE p() ALTER TABLE t ENGINE InnoDB PARTITION BY LINEAR HASH ((1, 2)) PARTITIONS 3', $operation->toString());
        self::assertInstanceOf(M\Routine\CreateProcedure::class, $operation);
        self::assertInstanceOf(M\Alter\AlterTable::class, $operation->body);
    }

}
