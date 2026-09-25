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
use SqlCatalog\Text\Origin;
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

    public function testElementFindsAnElementByItsKey(): void
    {
        $term = new ArrayTerm([
            new ArrayEntry(null, Domain::literal('first')),
            new ArrayEntry(Domain::literal('k'), Domain::literal('keyed')),
            new ArrayEntry(null, Domain::literal('second')),
        ]);
        self::assertSame('first', $term->element(0)?->soleLiteral()?->value);
        self::assertSame('first', $term->element('0')?->soleLiteral()?->value);
        self::assertSame('second', $term->element(1)?->soleLiteral()?->value);
        self::assertSame('keyed', $term->element('k')?->soleLiteral()?->value);
        self::assertNull($term->element('missing'));
    }

    public function testElementNeverReadsAnElementUnderABooleanOrANullKey(): void
    {
        $term = new ArrayTerm([
            new ArrayEntry(null, Domain::literal('first')),
            new ArrayEntry(null, Domain::literal('second')),
            new ArrayEntry(Domain::literal(''), Domain::literal('empty')),
        ]);
        self::assertNull($term->element(true));
        self::assertNull($term->element(false));
        self::assertNull($term->element(null));
    }

    public function testAnyValueIsTheUnionOfEveryElement(): void
    {
        $term = new ArrayTerm([
            new ArrayEntry(Domain::literal('u'), Domain::literal('users')),
            new ArrayEntry(Domain::literal('a'), Domain::literal('admins')),
            new ArrayEntry(null, Domain::literal('users')),
        ]);
        self::assertSame('literal:string:admins|literal:string:users', $term->anyValue(Origin::Unresolved)?->signature());
    }

    public function testAnyValueOfAnArrayWithNoElementsIsNothing(): void
    {
        self::assertNull((new ArrayTerm([]))->anyValue(Origin::Unresolved));
        self::assertNull((new ArrayTerm([], false))->anyValue(Origin::Unresolved));
    }

    public function testAnyValueOfAnArrayKnownOnlyInPartKeepsAGapWhereItWasRead(): void
    {
        $term = new ArrayTerm([new ArrayEntry(null, Domain::literal('users'))], false);
        $values = $term->anyValue(Origin::Loop, 'iterated value');

        self::assertSame('literal:string:users|opaque:mixed:loop', $values?->signature());
        self::assertEquals(new OpaqueTerm(TypeShape::unknown(), Origin::Loop, 'iterated value'), $values->terms[1]);
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
