<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Evaluation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Core\Evaluation\OpaqueTerm;
use SqlCatalog\Core\Text\Origin;
use SqlCatalog\Core\Text\TextHole;
use SqlCatalog\Core\Text\TextPattern;
use SqlCatalog\Core\Type\TypeShape;

#[CoversClass(OpaqueTerm::class)]
#[UsesClass(TextHole::class)]
#[UsesClass(TextPattern::class)]
#[UsesClass(TypeShape::class)]
final class OpaqueTermTest extends TestCase
{
    public function testUnresolvedKnowsNothingAboutTheValue(): void
    {
        $term = OpaqueTerm::unresolved('$name');
        self::assertSame(Origin::Unresolved, $term->origin);
        self::assertSame('mixed', $term->type()->display());
        self::assertSame('$name', $term->expression);
    }

    public function testToPatternLeavesAGapCarryingTheOrigin(): void
    {
        $pattern = (new OpaqueTerm(TypeShape::of(['string']), Origin::External, 'readInput()', '$sql'))->toPattern();
        self::assertSame('{$}', $pattern->display());
        self::assertSame(Origin::External, $pattern->holes()[0]->origin);
        self::assertSame('readInput()', $pattern->holes()[0]->expression);
        self::assertSame('$sql', $pattern->holes()[0]->variable);
    }

    public function testTypeIsTheDeclaredOne(): void
    {
        self::assertSame('int', (new OpaqueTerm(TypeShape::of(['int']), Origin::Parameter))->type()->display());
    }

    public function testSignatureCombinesTypeAndOrigin(): void
    {
        $left = new OpaqueTerm(TypeShape::of(['int']), Origin::Parameter);
        $right = new OpaqueTerm(TypeShape::of(['int']), Origin::External);
        self::assertNotSame($left->signature(), $right->signature());
    }
}
