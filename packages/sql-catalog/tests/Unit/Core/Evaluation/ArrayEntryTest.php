<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Evaluation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Core\Evaluation\ArrayEntry;
use SqlCatalog\Core\Evaluation\Domain;
use SqlCatalog\Core\Evaluation\LiteralTerm;
use SqlCatalog\Core\Evaluation\OpaqueTerm;
use SqlCatalog\Core\Text\LiteralText;
use SqlCatalog\Core\Text\TextPattern;
use SqlCatalog\Core\Type\TypeShape;

#[CoversClass(ArrayEntry::class)]
#[UsesClass(Domain::class)]
#[UsesClass(LiteralTerm::class)]
#[UsesClass(OpaqueTerm::class)]
#[UsesClass(LiteralText::class)]
#[UsesClass(TextPattern::class)]
#[UsesClass(TypeShape::class)]
final class ArrayEntryTest extends TestCase
{
    public function testScalarKeyReadsAResolvedStringKey(): void
    {
        $entry = new ArrayEntry(Domain::literal(':id'), Domain::literal(1));
        self::assertSame(':id', $entry->scalarKey());
    }

    public function testScalarKeyReadsAResolvedIntegerKey(): void
    {
        self::assertSame(2, (new ArrayEntry(Domain::literal(2), Domain::literal('x')))->scalarKey());
    }

    public function testScalarKeyIsNullWithoutAKey(): void
    {
        self::assertNull((new ArrayEntry(null, Domain::literal('x')))->scalarKey());
    }

    public function testScalarKeyIsNullWhenTheKeyDidNotResolve(): void
    {
        self::assertNull((new ArrayEntry(Domain::unknown(), Domain::literal('x')))->scalarKey());
    }

    public function testScalarKeyIsNullForANonStringScalar(): void
    {
        self::assertNull((new ArrayEntry(Domain::literal(true), Domain::literal('x')))->scalarKey());
    }

    public function testSignatureCombinesKeyAndValue(): void
    {
        $keyed = new ArrayEntry(Domain::literal('a'), Domain::literal(1));
        $unkeyed = new ArrayEntry(null, Domain::literal(1));
        self::assertNotSame($keyed->signature(), $unkeyed->signature());
    }
}
