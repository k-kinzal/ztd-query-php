<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Routine\Program\Expression\Call;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Derivation;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Platform\MySql\Dialect;
use SqlSemantics\Platform\MySql\Statement\Expression\Row;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DeferredExtract;

#[CoversClass(DeferredExtract::class)]
#[Medium]
final class DeferredExtractTest extends TestCase
{
    public function testDeriveScalarKeepsThePreparationConditionOutOfDiagnostics(): void
    {
        $value = new DeferredExtract(\SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit::Day, new Row([new NumberLiteral('1'), new NumberLiteral('2')]));
        $derivation = new Derivation((new Semantics(Dialect::MySql))->context([]));
        $fact = $derivation->scalar($value, $derivation->environment());
        self::assertInstanceOf(\SqlSemantics\Statement\Type\Invalid::class, $fact->type);
        self::assertSame([], $derivation->facts()->diagnostics);
    }

    public function testRenderRetainsAllWrittenOperands(): void
    {
        $value = new DeferredExtract(\SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit::Day, new Row([new NumberLiteral('1'), new NumberLiteral('2')]));
        $platform = new \SqlSemantics\Platform\MySql\Platform();
        $out = new \SqlSemantics\Rendering\Output($platform->codec((new Semantics(Dialect::MySql))->profile()));
        $value->render($out);
        self::assertSame('EXTRACT(DAY FROM (1, 2))', (new \SqlSemantics\Rendering\Lexical())->join($out->pieces()));
    }

    public function testOperandsKeepsEveryPreparationDependency(): void
    {
        $value = new DeferredExtract(\SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit::Day, new Row([new NumberLiteral('1'), new NumberLiteral('2')]));
        self::assertCount(1, $value->operands());
    }

    public function testProblemRetainsTheFirstRowConstraint(): void
    {
        $value = new DeferredExtract(\SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit::Day, new Row([new NumberLiteral('1'), new NumberLiteral('2')]));
        $problem = $value->problem();
        self::assertInstanceOf(\SqlSemantics\Platform\MySql\Statement\Expression\Problem\OperandColumns::class, $problem);
        self::assertSame([1, 2], [$problem->expected, $problem->actual]);
    }
}
