<?php

declare(strict_types=1);

namespace Tests\Unit\Rendering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Piece;
use SqlSemantics\Rendering\PieceKind;

#[CoversClass(PieceKind::class)]
#[Small]
final class PieceKindTest extends TestCase
{
    public function testTheKindsAreKeywordSymbolNameAndLiteral(): void
    {
        self::assertSame(['Keyword', 'Symbol', 'Name', 'Literal'], array_map(static fn (PieceKind $kind): string => $kind->name, PieceKind::cases()));
    }

    public function testOnlyTheSymbolKindIsTightAgainstPunctuation(): void
    {
        $lexical = new Lexical();
        $name = new Piece(PieceKind::Name, 'a');

        self::assertTrue($lexical->tight($name, new Piece(PieceKind::Symbol, ',')));
        self::assertFalse($lexical->tight($name, new Piece(PieceKind::Literal, ',')));
    }
}
