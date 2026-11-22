<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlParser\Lexer\Token;
use SqlParser\Sqlite\SqliteParser;
use SqlSemantics\Platform\Sqlite\Platform;
use SqlSemantics\Platform\Sqlite\Rules\LeafKeys;
use SqlSemantics\Platform\Sqlite\Semantics;
use SqlSemantics\Testing\Sqlite\Spellings;

#[CoversClass(Spellings::class)]
#[Medium]
final class SpellingsTest extends TestCase
{
    public function testCheckAcceptsOtherSpellingsOfTheSameTokensAndTrivia(): void
    {
        $spellings = new Spellings(new SqliteParser(), Platform::shared()->productions((new Semantics())->profile()), new LeafKeys());

        $spellings->check('SELECT a AND b', "SELECT `a` /* x */\nand b");

        self::assertCount(4, $spellings->tokens('SELECT a AND b'));
    }

    public function testCheckRefusesADifferentToken(): void
    {
        $spellings = new Spellings(new SqliteParser(), Platform::shared()->productions((new Semantics())->profile()), new LeafKeys());

        $this->expectExceptionMessage('The spelled SQL is not the rendered SQL at token 2: source has AND:AND, rendered SQL has OR:OR: SELECT a OR b');

        $spellings->check('SELECT a AND b', 'SELECT a OR b');
    }

    public function testCheckRefusesATokenWrittenIntoAGap(): void
    {
        $spellings = new Spellings(new SqliteParser(), Platform::shared()->productions((new Semantics())->profile()), new LeafKeys());

        $this->expectExceptionMessage('The spelled SQL has 5 tokens where the rendered SQL has 4: SELECT a AND NOT b');

        $spellings->check('SELECT a AND b', 'SELECT a AND NOT b');
    }

    public function testTokensDropsTheEndMarker(): void
    {
        $tokens = (new Spellings(new SqliteParser(), Platform::shared()->productions((new Semantics())->profile()), new LeafKeys()))->tokens(" -- only a comment\n");

        self::assertSame([], array_map(static fn (Token $token): string => $token->text, $tokens));
    }

    public function testTreeRefusesTextOutsideTheGrammar(): void
    {
        $spellings = new Spellings(new SqliteParser(), Platform::shared()->productions((new Semantics())->profile()), new LeafKeys());

        $this->expectExceptionMessage('A rendered or spelled text is outside the grammar: SELECT FROM');

        $spellings->tree('SELECT FROM');
    }
}
