<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Program\Expression\Branching;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Expression\Row;
use SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredProjection;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredSelection;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Branching\DeferredCase;

#[CoversClass(DeferredCase::class)]
#[Medium]
final class DeferredCaseTest extends TestCase
{
    public function testDeriveScalarBindsWithoutInvokingTheCondition(): void
    {
        $row = new Row([new NumberLiteral('1'), new NumberLiteral('2')]);
        $value = new DeferredCase(null, [new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Branching\DeferredCaseBranch(new NumberLiteral('0'), $row)], new NullLiteral());
        $derivation = new Derivation((new Semantics(Dialect::MySql))->context([]));
        $fact = $derivation->scalar($value, $derivation->environment());
        self::assertInstanceOf(\SqlSemantics\Statement\Type\Invalid::class, $fact->type);
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testOperandsRetainsTheCompletePreparationOrder(): void
    {
        $row = new Row([new NumberLiteral('1'), new NumberLiteral('2')]);
        $value = new DeferredCase(null, [new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Branching\DeferredCaseBranch(new NumberLiteral('0'), $row)], new NullLiteral());
        self::assertCount(3, $value->operands());
        self::assertSame($row, $value->operands()[1]);
    }

    public function testProblemRetainsTheActualRowWidth(): void
    {
        $row = new Row([new NumberLiteral('1'), new NumberLiteral('2')]);
        $value = new DeferredCase(null, [new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Branching\DeferredCaseBranch(new NumberLiteral('0'), $row)], new NullLiteral());
        $problem = $value->problem();
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Expression\Problem\OperandColumns::class, $problem);
        self::assertSame([1, 2], [$problem->expected, $problem->actual]);
    }

    public function testRenderRoundTripsEveryRetainedRole(): void
    {
        $row = new Row([new NumberLiteral('1'), new NumberLiteral('2')]);
        $value = new DeferredCase(null, [new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Branching\DeferredCaseBranch(new NumberLiteral('0'), $row)], new NullLiteral());
        $sql = 'CREATE PROCEDURE p() SELECT CASE WHEN 0 THEN (1, 2) ELSE NULL END';
        $semantics = new Semantics(Dialect::MySql);
        $operation = $semantics->analyze($sql);
        self::assertInstanceOf(CreateProcedure::class, $operation->statement);
        self::assertInstanceOf(DeferredSelection::class, $operation->statement->body);
        $item = $operation->statement->body->items[0];
        self::assertInstanceOf(DeferredProjection::class, $item);
        self::assertEquals($value, $item->expression);
        self::assertSame($sql, $operation->toString());
        self::assertSame($sql, $semantics->analyze($operation->toString())->toString());
    }
}
