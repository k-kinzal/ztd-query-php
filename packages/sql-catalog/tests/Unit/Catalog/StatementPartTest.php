<?php

declare(strict_types=1);

namespace Tests\Unit\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Catalog\StatementPart;
use SqlCatalog\Text\LiteralText;
use SqlCatalog\Text\Origin;
use SqlCatalog\Text\TextHole;
use SqlCatalog\Text\TextPattern;
use SqlCatalog\Type\TypeShape;

#[CoversClass(StatementPart::class)]
#[UsesClass(LiteralText::class)]
#[UsesClass(Origin::class)]
#[UsesClass(TextHole::class)]
#[UsesClass(TextPattern::class)]
#[UsesClass(TypeShape::class)]
final class StatementPartTest extends TestCase
{
    public function testOfKeepsAKnownRunAsTheTextItIs(): void
    {
        $parts = StatementPart::of(TextPattern::fromText('SELECT 1'));

        self::assertCount(1, $parts);
        self::assertSame('SELECT 1', $parts[0]->text);
        self::assertFalse($parts[0]->isGap);
        self::assertSame('', $parts[0]->origin);
    }

    public function testOfKeepsWhatIsKnownAboutAGap(): void
    {
        $parts = StatementPart::of(TextPattern::fromHole(
            new TextHole(Origin::External, TypeShape::unknown(), '$_GET["id"]', '$id'),
        ));

        self::assertCount(1, $parts);
        self::assertTrue($parts[0]->isGap);
        self::assertSame('', $parts[0]->text);
        self::assertSame('external', $parts[0]->origin);
        self::assertSame('external input', $parts[0]->reason);
        self::assertSame('$_GET["id"]', $parts[0]->expression);
        self::assertSame('$id', $parts[0]->variable);
    }

    public function testOfKeepsTheRunsAndTheGapsInOrder(): void
    {
        $parts = StatementPart::of(TextPattern::fromSegments([
            new LiteralText('SELECT * FROM '),
            new TextHole(Origin::Property, TypeShape::unknown()),
            new LiteralText(' WHERE id = 1'),
        ]));

        self::assertSame(
            ['SELECT * FROM ', '', ' WHERE id = 1'],
            array_map(static fn (StatementPart $part): string => $part->text, $parts),
        );
        self::assertSame([false, true, false], array_map(static fn (StatementPart $part): bool => $part->isGap, $parts));
    }

    public function testOfHasNothingToSayAboutAnEmptyStatement(): void
    {
        self::assertSame([], StatementPart::of(TextPattern::empty()));
    }
}
