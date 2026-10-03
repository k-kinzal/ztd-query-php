<?php

declare(strict_types=1);

namespace Tests\Unit\Rendering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Rendering\Piece;
use SqlSemantics\Rendering\PieceKind;

#[CoversClass(Piece::class)]
#[Small]
final class PieceTest extends TestCase
{
    public function testAPieceKeepsItsKindSpellingAndGlue(): void
    {
        $piece = new Piece(PieceKind::Symbol, ',', true);

        self::assertSame(PieceKind::Symbol, $piece->kind);
        self::assertSame(',', $piece->text);
        self::assertTrue($piece->glued);
    }

    public function testAPieceIsNotGluedByDefault(): void
    {
        self::assertFalse((new Piece(PieceKind::Keyword, 'SELECT'))->glued);
    }
}
