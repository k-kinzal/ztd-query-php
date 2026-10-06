<?php

declare(strict_types=1);

namespace Tests\Unit\Rendering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Piece;
use SqlSemantics\Rendering\PieceKind;

#[CoversClass(Lexical::class)]
#[Small]
final class LexicalTest extends TestCase
{
    public function testJoinSeparatesPiecesWithOneSpaceExceptAroundTightPunctuation(): void
    {
        $sql = (new Lexical())->join([
            new Piece(PieceKind::Keyword, 'SELECT'),
            new Piece(PieceKind::Name, 'f'),
            new Piece(PieceKind::Symbol, '(', true),
            new Piece(PieceKind::Name, 't'),
            new Piece(PieceKind::Symbol, '.'),
            new Piece(PieceKind::Name, 'a'),
            new Piece(PieceKind::Symbol, ','),
            new Piece(PieceKind::Literal, "'x'"),
            new Piece(PieceKind::Symbol, ')'),
            new Piece(PieceKind::Symbol, ';'),
        ]);

        self::assertSame("SELECT f(t.a, 'x');", $sql);
    }

    public function testJoinHonoursGlueAndWritesNothingForNoPieces(): void
    {
        $sql = (new Lexical())->join([
            new Piece(PieceKind::Symbol, '-'),
            new Piece(PieceKind::Literal, '1', true),
            new Piece(PieceKind::Symbol, '+'),
            new Piece(PieceKind::Literal, '2'),
        ]);

        self::assertSame('-1 + 2', $sql);
        self::assertSame('', (new Lexical())->join([]));
    }

    public function testTightDecidesByTheSymbolOnEitherSide(): void
    {
        $lexical = new Lexical();
        $name = new Piece(PieceKind::Name, 'a');

        self::assertTrue($lexical->tight($name, new Piece(PieceKind::Symbol, ')')));
        self::assertTrue($lexical->tight($name, new Piece(PieceKind::Symbol, ']')));
        self::assertTrue($lexical->tight(new Piece(PieceKind::Symbol, '['), $name));
        self::assertTrue($lexical->tight($name, new Piece(PieceKind::Keyword, 'AS', true)));
        self::assertFalse($lexical->tight($name, new Piece(PieceKind::Symbol, '=')));
        self::assertFalse($lexical->tight(new Piece(PieceKind::Symbol, ')'), $name));
        self::assertFalse($lexical->tight(new Piece(PieceKind::Keyword, '('), $name));
    }
}
