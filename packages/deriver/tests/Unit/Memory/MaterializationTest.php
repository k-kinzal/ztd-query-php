<?php

declare(strict_types=1);

namespace Tests\Unit\Memory;

use Deriver\Memory\Materialization;
use Deriver\Memory\Memory;
use Deriver\Value\Term;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Memory\Materialization
 */
#[CoversClass(Materialization::class)]
#[UsesClass(\Deriver\Memory\Location::class)]
#[UsesClass(Memory::class)]
#[UsesClass(Term::class)]
#[Small]
final class MaterializationTest extends TestCase
{
    public function testReadSharesRepeatedArraySubgraphs(): void
    {
        $value = \Tests\Fake\ValueDocument::shared(64, Term::constant(1));
        $projected = (new Materialization(new Memory()))->read($value);
        self::assertTrue($projected->operands[0] === $projected->operands[1]);
        self::assertTrue($projected->isConcrete());
    }
    public function testReadKeepsReferenceValuesCurrentAcrossMemoryViews(): void
    {
        $memory = new Memory();
        $location = $memory->allocate(Term::constant(1));
        $value = Term::array([new Term('cell', $location->root)]);
        $before = (new Materialization($memory))->read($value);
        $memory->write($location, Term::constant(2));
        $after = (new Materialization($memory))->read($value);
        self::assertSame([1], $before->native());
        self::assertSame([2], $after->native());
    }
}
