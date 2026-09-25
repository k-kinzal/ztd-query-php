<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Evaluation\ArrayTerm;
use SqlCatalog\Evaluation\LiteralTerm;
use SqlCatalog\Evaluation\ObjectTerm;
use SqlCatalog\Evaluation\OpaqueTerm;
use SqlCatalog\Evaluation\PatternTerm;
use SqlCatalog\Evaluation\Term;
use SqlCatalog\Text\LiteralText;
use SqlCatalog\Text\TextHole;
use SqlCatalog\Text\TextPattern;
use SqlCatalog\Type\TypeShape;

#[CoversClass(Term::class)]
#[UsesClass(ArrayTerm::class)]
#[UsesClass(LiteralTerm::class)]
#[UsesClass(ObjectTerm::class)]
#[UsesClass(OpaqueTerm::class)]
#[UsesClass(PatternTerm::class)]
#[UsesClass(LiteralText::class)]
#[UsesClass(TextHole::class)]
#[UsesClass(TextPattern::class)]
#[UsesClass(TypeShape::class)]
final class TermTest extends TestCase
{
    public function testToPatternIsExactOnlyForResolvedTerms(): void
    {
        self::assertTrue((new LiteralTerm('x'))->toPattern()->isExact());
        self::assertFalse(OpaqueTerm::unresolved()->toPattern()->isExact());
        self::assertFalse((new ArrayTerm([]))->toPattern()->isExact());
        self::assertFalse((new ObjectTerm('PDO'))->toPattern()->isExact());
    }

    public function testTypeIsReportedByEveryKindOfTerm(): void
    {
        self::assertSame('string', (new LiteralTerm('x'))->type()->display());
        self::assertSame('string', (new PatternTerm(TextPattern::fromText('x')))->type()->display());
        self::assertSame('array', (new ArrayTerm([]))->type()->display());
    }

    public function testSignatureSeparatesTermsOfDifferentKinds(): void
    {
        $signatures = [
            (new LiteralTerm('x'))->signature(),
            (new ArrayTerm([]))->signature(),
            (new ObjectTerm('PDO'))->signature(),
            OpaqueTerm::unresolved()->signature(),
        ];
        self::assertCount(4, array_unique($signatures));
    }
}
