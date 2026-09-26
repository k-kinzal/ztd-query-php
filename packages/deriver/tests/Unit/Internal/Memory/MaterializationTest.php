<?php

declare(strict_types=1);

namespace Tests\Unit\Internal\Memory;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Internal\Memory\Materialization
 */
#[CoversClass(\Deriver\Internal\Memory\Materialization::class)]
#[UsesClass(\Deriver\Internal\Memory\Location::class)]
#[UsesClass(\Deriver\Internal\Memory\Memory::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[Small]
final class MaterializationTest extends TestCase
{
    public function testReadSharesRepeatedArraySubgraphs(): void
    {
        $value = \Tests\Fake\ValueDocument::shared(64, \Deriver\Value\Term::constant(1));
        $projected = (new \Deriver\Internal\Memory\Materialization(new \Deriver\Internal\Memory\Memory()))->read($value);
        self::assertTrue($projected->operands[0] === $projected->operands[1]);
        self::assertTrue($projected->isConcrete());
    }
    public function testReadKeepsReferenceValuesCurrentAcrossMemoryViews(): void
    {
        $memory = new \Deriver\Internal\Memory\Memory();
        $location = $memory->allocate(\Deriver\Value\Term::constant(1));
        $value = \Deriver\Value\Term::array([new \Deriver\Value\Term('cell', $location->root)]);
        $before = (new \Deriver\Internal\Memory\Materialization($memory))->read($value);
        $memory->write($location, \Deriver\Value\Term::constant(2));
        $after = (new \Deriver\Internal\Memory\Materialization($memory))->read($value);
        self::assertSame([1], $before->native());
        self::assertSame([2], $after->native());
    }
}
