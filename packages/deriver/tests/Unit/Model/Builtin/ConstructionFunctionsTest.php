<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Builtin;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Small]
final class ConstructionFunctionsTest extends TestCase
{
    public function testApplyBoundsAllocationAndReportsInvalidCounts(): void
    {
        $functions = new \Deriver\Model\Builtin\ConstructionFunctions();
        self::assertSame('ValueError', $functions->apply('array_fill', [\Deriver\Value\Term::constant(0), \Deriver\Value\Term::constant(-1), \Deriver\Value\Term::constant('?')])->literal);
        self::assertSame('opaque', $functions->apply('str_repeat', [\Deriver\Value\Term::constant('x'), \Deriver\Value\Term::constant(PHP_INT_MAX)])->kind);
    }
    public function testIntegerUsesExplicitBase(): void
    {
        $functions = new \Deriver\Model\Builtin\ConstructionFunctions();
        self::assertSame(255, $functions->integer([\Deriver\Value\Term::constant('0xff'), \Deriver\Value\Term::constant(0)])->native());
    }
}
