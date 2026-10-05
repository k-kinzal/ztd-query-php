<?php

declare(strict_types=1);

namespace Tests\Unit\Rendering;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Platform\Sqlite\Rendering\Codec;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\IntegerLiteral;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\NullLiteral;
use SqlSemantics\Rendering\Lexical;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Rendering\Piece;
use SqlSemantics\Rendering\PieceKind;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Spelling\Layout;
use SqlSemantics\Statement\Spelling\Spelled;

#[CoversClass(Output::class)]
#[Small]
final class OutputTest extends TestCase
{
    public function testKeywordWritesEachUpperCaseWordAsAPiece(): void
    {
        $output = new Output(new Codec());

        $output->keyword('ORDER', 'BY');

        self::assertSame(['ORDER', 'BY'], array_map(static fn (Piece $piece): string => $piece->text, $output->pieces()));
        self::assertSame(PieceKind::Keyword, $output->pieces()[0]->kind);
    }

    public function testKeywordRefusesAnythingButOneUpperCaseWord(): void
    {
        $output = new Output(new Codec());

        $this->expectExceptionMessage('A keyword piece is one upper-case word.');

        $output->keyword('order by');
    }

    public function testSymbolWritesPunctuationOnly(): void
    {
        $output = new Output(new Codec());

        $output->symbol('(')->symbol('<>')->symbol('->>');

        self::assertSame(['(', '<>', '->>'], array_map(static fn (Piece $piece): string => $piece->text, $output->pieces()));
    }

    public function testSymbolRefusesLetters(): void
    {
        $output = new Output(new Codec());

        $this->expectExceptionMessage('A symbol piece contains punctuation only.');

        $output->symbol('IS');
    }

    public function testNameIsSpelledByTheCodecForItsPosition(): void
    {
        $output = new Output(new Codec());

        $output->name(new Name('order'), NameUse::Relation)->name(new Name('a'));

        self::assertSame(['`order`', 'a'], array_map(static fn (Piece $piece): string => $piece->text, $output->pieces()));
        self::assertSame(PieceKind::Name, $output->pieces()[0]->kind);
    }

    public function testSpelledWritesALiteralSpelling(): void
    {
        $output = new Output(new Codec());

        $output->spelled("'it''s'");

        self::assertSame("'it''s'", $output->pieces()[0]->text);
        self::assertSame(PieceKind::Literal, $output->pieces()[0]->kind);
    }

    public function testSpelledRefusesAnEmptySpelling(): void
    {
        $output = new Output(new Codec());

        $this->expectExceptionMessage('A spelled piece is not empty.');

        $output->spelled('');
    }

    public function testGlueJoinsOnlyTheNextPiece(): void
    {
        $output = new Output(new Codec());

        $output->symbol('-')->glue()->spelled('1')->spelled('2');

        self::assertSame([false, true, false], array_map(static fn (Piece $piece): bool => $piece->glued, $output->pieces()));
    }

    public function testNodeWritesAChildAndNothingForNull(): void
    {
        $output = new Output(new Codec());

        $output->node(new IntegerLiteral('42'))->node(null)->node(new NullLiteral());

        self::assertSame(['42', 'NULL'], array_map(static fn (Piece $piece): string => $piece->text, $output->pieces()));
    }

    public function testListSeparatesChildrenWithTheSymbol(): void
    {
        $output = new Output(new Codec());

        $output->list([new IntegerLiteral('1'), new IntegerLiteral('2'), new IntegerLiteral('3')])->list([], ',')->list([new IntegerLiteral('4')], ';');

        self::assertSame(['1', ',', '2', ',', '3', '4'], array_map(static fn (Piece $piece): string => $piece->text, $output->pieces()));
    }

    public function testAddRecordsAPieceAndConsumesAPendingGlue(): void
    {
        $output = new Output(new Codec());

        $output->glue();
        $output->add(PieceKind::Literal, 'x');
        $output->add(PieceKind::Literal, 'y');

        self::assertTrue($output->pieces()[0]->glued);
        self::assertFalse($output->pieces()[1]->glued);
    }

    public function testPiecesAreEmptyBeforeAnythingIsWritten(): void
    {
        self::assertSame([], (new Output(new Codec()))->pieces());
    }

    public function testLayoutRespellsEachRenderedPieceAndKeepsTheRenderedText(): void
    {
        $output = new Output(new Codec());
        $layout = new Layout([new Spelled('', 'null')]);

        $output->keyword('SELECT')->layout($layout, new NullLiteral());

        self::assertSame(['SELECT', 'null'], array_map(static fn (Piece $piece): string => $piece->text, $output->pieces()));
        self::assertSame(['SELECT', 'NULL'], array_map(static fn (Piece $piece): string => $piece->text, $output->canonical()));
        self::assertSame('SELECT null', (new Lexical())->join($output->pieces()));
    }

    public function testLayoutWithoutALayoutRendersRegularly(): void
    {
        $output = new Output(new Codec());

        $output->layout(null, new IntegerLiteral('7'));

        self::assertSame(['7'], array_map(static fn (Piece $piece): string => $piece->text, $output->pieces()));
        self::assertSame(['7'], array_map(static fn (Piece $piece): string => $piece->text, $output->canonical()));
    }

    public function testLayoutRefusesALayoutThatDoesNotAlignWithTheRenderedPieces(): void
    {
        $output = new Output(new Codec());

        $this->expectExceptionMessage('The layout spells 2 tokens where the rendering writes 1.');

        $output->layout(new Layout([new Spelled('', '7'), new Spelled(' ', '8')]), new IntegerLiteral('7'));
    }

    public function testCanonicalKeepsTheRenderedTextOfEveryPiece(): void
    {
        $output = (new Output(new Codec()))->keyword('SELECT')->layout(new Layout([new Spelled('', '7')]), new IntegerLiteral('7'));

        self::assertSame(['SELECT', '7'], array_map(static fn (Piece $piece): string => $piece->text, $output->canonical()));
    }
}
