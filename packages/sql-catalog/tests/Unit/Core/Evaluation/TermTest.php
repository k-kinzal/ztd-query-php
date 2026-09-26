<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Evaluation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Core\Evaluation\ArrayTerm;
use SqlCatalog\Core\Evaluation\LiteralTerm;
use SqlCatalog\Core\Evaluation\ObjectTerm;
use SqlCatalog\Core\Evaluation\OpaqueTerm;
use SqlCatalog\Core\Evaluation\PatternTerm;
use SqlCatalog\Core\Evaluation\Term;
use SqlCatalog\Core\Text\LiteralText;
use SqlCatalog\Core\Text\TextHole;
use SqlCatalog\Core\Text\TextPattern;
use SqlCatalog\Core\Type\TypeShape;

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
