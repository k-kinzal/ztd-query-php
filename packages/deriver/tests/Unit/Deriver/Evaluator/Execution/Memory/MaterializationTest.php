<?php

declare (strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Execution\Memory;

use Deriver\Deriver\Evaluator\Execution\Memory\Location;
use Deriver\Deriver\Evaluator\Execution\Memory\Materialization;
use Deriver\Deriver\Evaluator\Execution\Memory\Memory;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Evaluator\TermKind;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Deriver\Evaluator\Execution\Memory\Materialization
 */
#[CoversClass(Materialization::class)]
#[UsesClass(Location::class)]
#[UsesClass(Memory::class)]
#[UsesClass(Term::class)]
#[Small]
final class MaterializationTest extends TestCase
{
    public function testReadSharesRepeatedArraySubgraphs(): void
    {
        $depth = 64;
        $leaf = Term::constant(1);
        $leaf = array_reduce(array_fill(0, $depth, null), static fn (Term $leaf): Term => Term::array([$leaf, $leaf]), $leaf);
        $value = $leaf;
        $projected = (new Materialization(new Memory()))->read($value);
        self::assertTrue($projected->operands[0] === $projected->operands[1]);
        self::assertTrue($projected->isConcrete());
    }
    public function testReadKeepsReferenceValuesCurrentAcrossMemoryViews(): void
    {
        $memory = new Memory();
        $location = $memory->allocate(Term::constant(1));
        $value = Term::array([new Term(TermKind::Cell, $location->root)]);
        $before = (new Materialization($memory))->read($value);
        $memory->write($location, Term::constant(2));
        $after = (new Materialization($memory))->read($value);
        self::assertSame([1], $before->native());
        self::assertSame([2], $after->native());
    }
}
