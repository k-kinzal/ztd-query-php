<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Program\Expression\Constructor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Expression\Row;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Constructor\DeferredRow;

#[CoversClass(DeferredRow::class)]
#[Medium]
final class DeferredRowTest extends TestCase
{
    public function testDeriveScalarKeepsThePreparationConditionOutOfDiagnostics(): void
    {
        $value = new DeferredRow([new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Predicate\DeferredRegexp(new Row([new NumberLiteral('1'), new NumberLiteral('2')]), new NumberLiteral('3')), new NumberLiteral('4')]);
        $derivation = new Derivation((new Semantics(Dialect::MySql))->context([]));
        $fact = $derivation->scalar($value, $derivation->environment());
        self::assertInstanceOf(\SqlSemantics\Statement\Type\Invalid::class, $fact->type);
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testRenderRetainsAllWrittenOperands(): void
    {
        $value = new DeferredRow([new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Predicate\DeferredRegexp(new Row([new NumberLiteral('1'), new NumberLiteral('2')]), new NumberLiteral('3')), new NumberLiteral('4')]);
        $platform = new \SqlSemantics\Platform\MySql\Platform();
        $out = new \SqlSemantics\Rendering\Output($platform->codec((new Semantics(Dialect::MySql))->profile()));
        $value->render($out);
        self::assertSame('((1, 2) REGEXP 3, 4)', (new \SqlSemantics\Rendering\Lexical())->join($out->pieces()));
    }

    public function testOperandsKeepsEveryPreparationDependency(): void
    {
        $value = new DeferredRow([new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Predicate\DeferredRegexp(new Row([new NumberLiteral('1'), new NumberLiteral('2')]), new NumberLiteral('3')), new NumberLiteral('4')]);
        self::assertCount(2, $value->operands());
    }

    public function testProblemRetainsTheFirstRowConstraint(): void
    {
        $value = new DeferredRow([new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Predicate\DeferredRegexp(new Row([new NumberLiteral('1'), new NumberLiteral('2')]), new NumberLiteral('3')), new NumberLiteral('4')]);
        $problem = $value->problem();
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Expression\Problem\OperandColumns::class, $problem);
        self::assertSame([1, 2], [$problem->expected, $problem->actual]);
    }
}
