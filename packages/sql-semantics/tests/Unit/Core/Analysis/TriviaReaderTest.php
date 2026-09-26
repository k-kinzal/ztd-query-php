<?php

declare(strict_types=1);

namespace Tests\Unit\Core\Analysis;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Core\Analysis\SourceComments;
use SqlSemantics\Core\Analysis\TriviaReader;

#[CoversClass(TriviaReader::class)]
#[UsesClass(SourceComments::class)]
#[Small]
final class TriviaReaderTest extends TestCase
{
    public function testReadSeparatesLeadingTrailingAndTokenCommentsAndSkipsTokensThatSpellNothing(): void
    {
        $select = new Token(1, 'SELECT', 'SELECT', 11, '/* lead */ ');
        $one = new Token(2, 'NUM', '1', 26, ' /* value */ ');
        $end = new Token(0, 'END_OF_INPUT', '', 33, " -- after\n");
        $tree = new Node('root', 0, [new Node('stmt', 0, [$select, new Node('expr', 0, [$one])]), $end], "-- more\n");
        $comments = (new TriviaReader())->read($tree);
        self::assertSame(['/* lead */'], $comments->leading);
        self::assertSame(['/* value */'], $comments->before($one));
        self::assertSame([], $comments->before($select));
        self::assertSame([], $comments->before($end));
        self::assertSame(['-- after', '-- more'], $comments->trailing);
    }

    public function testReadStartsEachTreeOutsideAnExecutableComment(): void
    {
        $reader = new TriviaReader(executableVersion: 80000);
        $opened = new Node('root', 0, [new Token(1, 'SELECT', 'SELECT', 0), new Token(2, 'NUM', '1', 16, ' /*!50700 ')]);
        $reader->read($opened);
        $plain = new Node('root', 0, [new Token(1, 'SELECT', 'SELECT', 0), new Token(2, 'NUM', '1', 14, ' /* a */ ')]);
        self::assertSame(['/* a */'], $reader->read($plain)->before($plain->tokens()[1]));
    }

    public function testCommentsSplitsBlockAndLineCommentsWithoutTheirWhitespace(): void
    {
        $reader = new TriviaReader();
        self::assertSame([], $reader->comments(''));
        self::assertSame([], $reader->comments(" \n\t"));
        self::assertSame(['/* a */', '-- b', '#c', '/* d'], $reader->comments("  /* a */\n-- b \r\n#c\n /* d"));
        self::assertSame(['/* a /* b */'], $reader->comments('/* a /* b */'));
    }

    public function testCommentsNestsBlockCommentsWhenTheLanguageDoes(): void
    {
        self::assertSame(['/* a /* b */ c */', '-- d'], (new TriviaReader(nestedBlocks: true))->comments("/* a /* b */ c */ -- d\n"));
    }

    public function testCommentsKeepsExecutableCommentDelimitersApartAndOrdinaryVersionCommentsWhole(): void
    {
        $reader = new TriviaReader(executableVersion: 80000);
        self::assertSame(['/*!50700'], $reader->comments(' /*!50700 '));
        self::assertSame(['/* inner */', '*/'], $reader->comments(' /* inner */ */ '));
        self::assertSame(['/*!99999 skipped */', '/*!'], $reader->comments(' /*!99999 skipped */ /*! '));
        self::assertSame(['*/'], $reader->comments(' */ '));
        self::assertSame(['/* a /*! b */ c */'], $reader->comments('/* a /*! b */ c */'));
    }


    public function testCommentEndFindsTheEndOfEachCommentKind(): void
    {
        $reader = new TriviaReader();
        self::assertSame(4, $reader->commentEnd("-- a\nb", 0));
        self::assertSame(2, $reader->commentEnd('#a', 0));
        self::assertSame(8, $reader->commentEnd(' /* a */ ', 1));
    }

    public function testBlockEndStepsOverPairsOnlyInLanguagesWithExecutableComments(): void
    {
        self::assertSame(5, (new TriviaReader())->blockEnd('/*/*/ x */', 0));
        self::assertSame(10, (new TriviaReader(executableVersion: 80000))->blockEnd('/*/*/ x */', 0));
        self::assertSame(10, (new TriviaReader(nestedBlocks: true))->blockEnd('/* /* */*/', 0));
        self::assertSame(7, (new TriviaReader())->blockEnd('/* open', 0));
    }
}
