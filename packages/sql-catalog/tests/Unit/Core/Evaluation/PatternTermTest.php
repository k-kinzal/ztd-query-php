<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Evaluation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Core\Evaluation\PatternTerm;
use SqlCatalog\Core\Text\LiteralText;
use SqlCatalog\Core\Text\Origin;
use SqlCatalog\Core\Text\TextHole;
use SqlCatalog\Core\Text\TextPattern;
use SqlCatalog\Core\Type\TypeShape;

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
