<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Evaluation\PatternTerm;
use SqlCatalog\Text\LiteralText;
use SqlCatalog\Text\Origin;
use SqlCatalog\Text\TextHole;
use SqlCatalog\Text\TextPattern;
use SqlCatalog\Type\TypeShape;

#[CoversClass(PatternTerm::class)]
#[UsesClass(LiteralText::class)]
#[UsesClass(TextHole::class)]
#[UsesClass(TextPattern::class)]
#[UsesClass(TypeShape::class)]
final class PatternTermTest extends TestCase
{
    public function testToPatternIsThePatternItself(): void
    {
        $pattern = TextPattern::fromText('WHERE id = ')
            ->concat(TextPattern::fromHole(new TextHole(Origin::External, TypeShape::unknown())));
        self::assertSame('WHERE id = {$}', (new PatternTerm($pattern))->toPattern()->display());
    }

    public function testTypeIsAlwaysString(): void
    {
        self::assertSame('string', (new PatternTerm(TextPattern::fromText('x')))->type()->display());
    }

    public function testSignatureDistinguishesDifferentShapes(): void
    {
        $left = new PatternTerm(TextPattern::fromText('a'));
        $right = new PatternTerm(TextPattern::fromText('b'));
        self::assertNotSame($left->signature(), $right->signature());
    }
}
