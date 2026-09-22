<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Evaluation\ArrayEntry;
use SqlCatalog\Evaluation\ArrayTerm;
use SqlCatalog\Evaluation\Domain;
use SqlCatalog\Evaluation\LiteralTerm;
use SqlCatalog\Evaluation\OpaqueTerm;
use SqlCatalog\Text\TextHole;
use SqlCatalog\Text\TextPattern;
use SqlCatalog\Type\TypeShape;

#[CoversClass(ArrayTerm::class)]
#[UsesClass(ArrayEntry::class)]
#[UsesClass(Domain::class)]
#[UsesClass(LiteralTerm::class)]
#[UsesClass(OpaqueTerm::class)]
#[UsesClass(TextHole::class)]
#[UsesClass(TextPattern::class)]
#[UsesClass(TypeShape::class)]
final class ArrayTermTest extends TestCase
{
    public function testPositionalKeepsUnkeyedAndIntegerKeyedElements(): void
    {
        $term = new ArrayTerm([
            new ArrayEntry(null, Domain::literal('a')),
            new ArrayEntry(Domain::literal(1), Domain::literal('b')),
            new ArrayEntry(Domain::literal(':x'), Domain::literal('c')),
        ]);
        self::assertCount(2, $term->positional());
        self::assertSame('a', $term->positional()[0]->soleLiteral()?->value);
    }

    public function testNamedKeepsStringKeyedElements(): void
    {
        $term = new ArrayTerm([
            new ArrayEntry(Domain::literal(':x'), Domain::literal('c')),
            new ArrayEntry(null, Domain::literal('a')),
        ]);
        self::assertSame([':x'], array_keys($term->named()));
    }

    public function testToPatternLeavesAGap(): void
    {
        self::assertSame('{$}', (new ArrayTerm([]))->toPattern()->display());
    }

    public function testTypeIsArray(): void
    {
        self::assertSame('array', (new ArrayTerm([]))->type()->display());
    }

    public function testSignatureRecordsWhetherEveryElementIsKnown(): void
    {
        self::assertNotSame((new ArrayTerm([], true))->signature(), (new ArrayTerm([], false))->signature());
    }
}
