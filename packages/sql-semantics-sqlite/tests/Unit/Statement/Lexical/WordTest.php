<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Lexical;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Rendering\Codec;
use SqlSemantics\Platform\Sqlite\Statement\Lexical\Word;
use SqlSemantics\Platform\Sqlite\Statement\Lexical\WordQuote;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;

#[CoversClass(Word::class)]
#[Small]
final class WordTest extends TestCase
{
    public function testBareTellsAnUnquotedWordFromAQuotedOne(): void
    {
        self::assertTrue((new Word(new Name('rowid')))->bare());
        self::assertFalse((new Word(new Name('rowid'), WordQuote::Double))->bare());
    }

    public function testIsComparesAnUnquotedWordWithoutRegardToCase(): void
    {
        self::assertTrue((new Word(new Name('RowId')))->is('ROWID'));
        self::assertFalse((new Word(new Name('rowids')))->is('ROWID'));
    }

    public function testIsNeverMatchesAQuotedWord(): void
    {
        self::assertFalse((new Word(new Name('rowid'), WordQuote::Double))->is('ROWID'));
        self::assertFalse((new Word(new Name('rowid'), WordQuote::Bracket))->is('ROWID'));
    }

    public function testSpellingDoublesTheEmbeddedClosingDelimiter(): void
    {
        self::assertSame('plain', (new Word(new Name('plain')))->spelling());
        self::assertSame("'it''s'", (new Word(new Name("it's"), WordQuote::Single))->spelling());
        self::assertSame('"a ""b"""', (new Word(new Name('a "b"'), WordQuote::Double))->spelling());
        self::assertSame('`a``b`', (new Word(new Name('a`b'), WordQuote::Backtick))->spelling());
        self::assertSame('[a b]', (new Word(new Name('a b'), WordQuote::Bracket))->spelling());
    }

    public function testSpellingAcceptsAKeywordTheGrammarLetsFallBackToAnIdentifier(): void
    {
        self::assertSame('KEY', (new Word(new Name('KEY')))->spelling());
    }

    public function testRenderWritesTheWordWithItsOwnQuoting(): void
    {
        $out = new Output(new Codec());
        (new Word(new Name('rowid'), WordQuote::Double))->render($out);

        self::assertSame('"rowid"', $out->pieces()[0]->text);
    }
}
