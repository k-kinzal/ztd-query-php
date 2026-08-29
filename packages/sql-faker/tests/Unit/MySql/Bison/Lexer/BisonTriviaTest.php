<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Bison\Lexer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Source\GrammarParseException;
use SqlFaker\Grammar\Source\SourceCursor;
use SqlFaker\MySql\Bison\Lexer\BisonTrivia;

#[CoversClass(BisonTrivia::class)]
#[UsesClass(GrammarParseException::class)]
#[UsesClass(SourceCursor::class)]
final class BisonTriviaTest extends TestCase
{
    public function testSkipFromLeavesTheCursorOnTheFirstLexeme(): void
    {
        $cursor = new SourceCursor("  \n /* a */ // b\n /* c */ foo");

        (new BisonTrivia())->skipFrom($cursor);

        self::assertSame('foo', $cursor->takeRest());
    }

    public function testSkipFromRunsToTheEndWhenOnlyTriviaRemains(): void
    {
        $cursor = new SourceCursor("/* a */\n  // b");

        (new BisonTrivia())->skipFrom($cursor);

        self::assertTrue($cursor->atEnd());
    }

    public function testSkipFromLeavesALexemeAlone(): void
    {
        $cursor = new SourceCursor('foo');

        (new BisonTrivia())->skipFrom($cursor);

        self::assertSame(0, $cursor->offset());
    }

    public function testSkipFromReportsASlashThatOpensNoComment(): void
    {
        $cursor = new SourceCursor('  / not a comment');

        $this->expectException(GrammarParseException::class);
        $this->expectExceptionMessage("Unexpected '/' at offset 2");

        (new BisonTrivia())->skipFrom($cursor);
    }

    public function testSkipCommentAtConsumesALineComment(): void
    {
        $cursor = new SourceCursor("// note\nfoo");

        self::assertTrue((new BisonTrivia())->skipCommentAt($cursor));
        self::assertSame("\nfoo", $cursor->takeRest());
    }

    public function testSkipCommentAtConsumesABlockComment(): void
    {
        $cursor = new SourceCursor('/* note */foo');

        self::assertTrue((new BisonTrivia())->skipCommentAt($cursor));
        self::assertSame('foo', $cursor->takeRest());
    }

    public function testSkipCommentAtLeavesALoneSlashInPlace(): void
    {
        $cursor = new SourceCursor('/ division');

        self::assertFalse((new BisonTrivia())->skipCommentAt($cursor));
        self::assertSame(0, $cursor->offset());
    }

    public function testSkipCommentAtLeavesAnythingElseInPlace(): void
    {
        $cursor = new SourceCursor('foo');

        self::assertFalse((new BisonTrivia())->skipCommentAt($cursor));
        self::assertSame(0, $cursor->offset());
    }

    public function testSkipToEndOfLineStopsBeforeTheNewline(): void
    {
        $cursor = new SourceCursor("// note\nfoo");

        self::assertTrue((new BisonTrivia())->skipToEndOfLine($cursor));
        self::assertSame("\nfoo", $cursor->takeRest());
    }

    public function testSkipToCommentCloseConsumesTheTerminator(): void
    {
        $cursor = new SourceCursor('/* note */foo');

        self::assertTrue((new BisonTrivia())->skipToCommentClose($cursor));
        self::assertSame('foo', $cursor->takeRest());
    }

    public function testSkipToCommentCloseRunsToTheEndWhenNeverClosed(): void
    {
        $cursor = new SourceCursor('/* never closed');

        self::assertTrue((new BisonTrivia())->skipToCommentClose($cursor));
        self::assertTrue($cursor->atEnd());
    }
}
