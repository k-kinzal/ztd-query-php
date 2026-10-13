<?php

declare(strict_types=1);

namespace Tests\Unit\Construction;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Construction\Reconstruction;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Statement\Node;

#[CoversClass(Reconstruction::class)]
#[Small]
final class ReconstructionTest extends TestCase
{
    public function testNodeConstructsFreshOccurrences(): void
    {
        $source = new Grouped(new NumberLiteral('1'));

        $copy = (new Reconstruction())->node($source);

        self::assertEquals($source, $copy);
        self::assertNotSame($source, $copy);
        self::assertInstanceOf(Grouped::class, $copy);
        self::assertNotSame($source->operand, $copy->operand);
    }

    public function testNodeSubstitutesAnOccurrenceThroughItsConstructor(): void
    {
        $copy = new Reconstruction(static fn (Node $node): ?Node => $node instanceof NumberLiteral ? new NumberLiteral('2') : null);

        self::assertEquals(new Grouped(new NumberLiteral('2')), $copy->node(new Grouped(new NumberLiteral('1'))));
    }

    public function testNodeChangesAnExplicitConstructorOperand(): void
    {
        $copy = (new Reconstruction())->node(new Grouped(new NumberLiteral('1')), ['operand' => new NumberLiteral('2')]);

        self::assertEquals(new Grouped(new NumberLiteral('2')), $copy);
    }

    public function testNodeRefusesAnUnknownConstructorOperand(): void
    {
        $this->expectExceptionMessage('Changed operands must name constructor parameters.');

        (new Reconstruction())->node(new Grouped(new NumberLiteral('1')), ['unknown' => null]);
    }

    public function testValueRetainsScalarDataAndCopiesListOccurrences(): void
    {
        $source = new NumberLiteral('1');
        $copy = (new Reconstruction())->value(['literal' => $source, 'label' => 'x', 'absent' => null]);

        self::assertEquals(['literal' => $source, 'label' => 'x', 'absent' => null], $copy);
        self::assertIsArray($copy);
        self::assertNotSame($source, $copy['literal']);
    }
}
