<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Sqlite\Lemon;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Sqlite\Lemon\LemonText;

#[CoversClass(LemonText::class)]
final class LemonTextTest extends TestCase
{
    public function testWithoutCommentsRemovesBlockAndLineComments(): void
    {
        self::assertSame(
            "cmd ::= SELECT. \n",
            (new LemonText())->withoutComments("/* a rule */cmd ::= SELECT. // and a note\n"),
        );
    }

    public function testWithoutCommentsLeavesGrammarAlone(): void
    {
        self::assertSame('cmd ::= SELECT.', (new LemonText())->withoutComments('cmd ::= SELECT.'));
    }

    public function testWithoutDirectiveBlocksRemovesTheCTheParserIsBuiltFrom(): void
    {
        self::assertSame(
            "\ncmd ::= SELECT.",
            (new LemonText())->withoutDirectiveBlocks("%include {cmd ::= NOTHING.}\ncmd ::= SELECT."),
        );
    }

    public function testWithoutDirectiveBlocksRemovesTheDeclarationsThatConfigureTheParser(): void
    {
        self::assertSame(
            "\ncmd ::= SELECT.",
            (new LemonText())->withoutDirectiveBlocks("%token_prefix TK_\ncmd ::= SELECT."),
        );
    }
}
