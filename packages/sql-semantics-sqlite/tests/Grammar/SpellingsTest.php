<?php

declare(strict_types=1);

namespace Tests\Grammar;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Token;
use SqlParser\Sqlite\SqliteParser;
use SqlSemantics\Platform\Sqlite\Platform;
use SqlSemantics\Platform\Sqlite\Rules\LeafKeys;
use SqlSemantics\Platform\Sqlite\Semantics;
use SqlSemantics\Testing\Sqlite\Spellings;

/**
 * Checks token spellings and trivia used to generate grammar variants.
 */
#[CoversClass(Spellings::class)]
#[Medium]
final class SpellingsTest extends TestCase
{
    /**
     * Equivalent token spellings can be separated by trivia.
     */
    public function testCheckAcceptsOtherSpellingsOfTheSameTokensAndTrivia(): void
    {
        $spellings = new Spellings(new SqliteParser(), Platform::shared()->productions((new Semantics())->profile()), new LeafKeys());

        $spellings->check('SELECT a AND b', "SELECT `a` /* x */\nand b");

        self::assertCount(4, $spellings->tokens('SELECT a AND b'));
    }

    /**
     * An edit that changes a token is rejected.
     */
    public function testCheckRefusesADifferentToken(): void
    {
        $spellings = new Spellings(new SqliteParser(), Platform::shared()->productions((new Semantics())->profile()), new LeafKeys());

        $this->expectExceptionMessage('The spelled SQL is not the rendered SQL at token 2: source has AND:AND, rendered SQL has OR:OR: SELECT a OR b');

        $spellings->check('SELECT a AND b', 'SELECT a OR b');
    }

    /**
     * A trivia edit cannot add a significant token.
     */
    public function testCheckRefusesATokenWrittenIntoAGap(): void
    {
        $spellings = new Spellings(new SqliteParser(), Platform::shared()->productions((new Semantics())->profile()), new LeafKeys());

        $this->expectExceptionMessage('The spelled SQL has 5 tokens where the rendered SQL has 4: SELECT a AND NOT b');

        $spellings->check('SELECT a AND b', 'SELECT a AND NOT b');
    }

    /**
     * The fixture excludes the lexer end marker.
     */
    public function testTokensDropsTheEndMarker(): void
    {
        $tokens = (new Spellings(new SqliteParser(), Platform::shared()->productions((new Semantics())->profile()), new LeafKeys()))->tokens(" -- only a comment\n");

        self::assertSame([], array_map(static fn (Token $token): string => $token->text, $tokens));
    }

    /**
     * A grammar variant must form a grammatical statement.
     */
    public function testTreeRefusesTextOutsideTheGrammar(): void
    {
        $spellings = new Spellings(new SqliteParser(), Platform::shared()->productions((new Semantics())->profile()), new LeafKeys());

        $this->expectExceptionMessage('A rendered or spelled text is outside the grammar: SELECT FROM');

        $spellings->tree('SELECT FROM');
    }
}
