<?php

declare(strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Builtin;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Small]
final class ConstructionFunctionsTest extends TestCase
{
    public function testApplyBoundsAllocationAndReportsInvalidCounts(): void
    {
        $functions = new \Deriver\Deriver\Evaluator\Builtin\ConstructionFunctions();
        self::assertSame('ValueError', $functions->apply('array_fill', [\Deriver\Deriver\Evaluator\Term::constant(0), \Deriver\Deriver\Evaluator\Term::constant(-1), \Deriver\Deriver\Evaluator\Term::constant('?')])->literal);
        self::assertSame(\Deriver\Deriver\Evaluator\TermKind::Opaque, $functions->apply('str_repeat', [\Deriver\Deriver\Evaluator\Term::constant('x'), \Deriver\Deriver\Evaluator\Term::constant(PHP_INT_MAX)])->kind);
    }
    public function testIntegerUsesExplicitBase(): void
    {
        $functions = new \Deriver\Deriver\Evaluator\Builtin\ConstructionFunctions();
        self::assertSame(255, $functions->integer([\Deriver\Deriver\Evaluator\Term::constant('0xff'), \Deriver\Deriver\Evaluator\Term::constant(0)])->native());
    }
}
