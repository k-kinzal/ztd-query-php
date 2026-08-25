<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\LexicalCatalog;
use SqlFaker\Grammar\SqlVersion;
use SqlFaker\PostgreSql\LexicalGrammar;
use SqlFaker\PostgreSql\ParserSemantics;

#[CoversClass(ParserSemantics::class)]
#[UsesClass(LexicalCatalog::class)]
#[UsesClass(LexicalGrammar::class)]
#[UsesClass(SqlVersion::class)]
final class ParserSemanticsTest extends TestCase
{
    public function testAppliedGivesEveryOptionInASetListAValue(): void
    {
        $semantics = new ParserSemantics(new LexicalGrammar(Factory::create(), 'pg-17.2'));

        self::assertSame(
            ['SET', '(', 'IDENT', '=', 'NONE', ')'],
            $semantics->applied(['SET', '(', 'IDENT', ')']),
        );
    }

    public function testAppliedGivesAnOperatorItsMissingArgument(): void
    {
        $semantics = new ParserSemantics(new LexicalGrammar(Factory::create(), 'pg-17.2'));

        self::assertSame(
            ['OPERATOR', 'IDENT', '(', 'NONE', ',', 'IDENT', ')'],
            $semantics->applied(['OPERATOR', 'IDENT', '(', 'IDENT', ')']),
        );
    }

    public function testTruncateQualifiedNamesCutsANameToTheDepthTheParserAccepts(): void
    {
        $semantics = new ParserSemantics(new LexicalGrammar(Factory::create(), 'pg-17.2'));

        self::assertSame(
            ['IDENT', '.', 'IDENT', '.', 'IDENT'],
            $semantics->truncateQualifiedNames(['IDENT', '.', 'IDENT', '.', 'IDENT', '.', 'IDENT']),
        );
    }

    public function testTruncateQualifiedNamesKeepsAStarThatEndsTheName(): void
    {
        $semantics = new ParserSemantics(new LexicalGrammar(Factory::create(), 'pg-17.2'));

        self::assertSame(['IDENT'], $semantics->truncateQualifiedNames(['IDENT', '.', '*']));
    }

    public function testMatchingParenAnswersWhereTheOpenedParenthesisCloses(): void
    {
        $semantics = new ParserSemantics(new LexicalGrammar(Factory::create(), 'pg-17.2'));

        self::assertSame(4, $semantics->matchingParen(['(', '(', 'IDENT', ')', ')'], 0));
    }

    public function testMatchingParenAnswersNothingWhenItNeverCloses(): void
    {
        $semantics = new ParserSemantics(new LexicalGrammar(Factory::create(), 'pg-17.2'));

        self::assertNull($semantics->matchingParen(['(', 'IDENT'], 0));
    }

    public function testIsIdentifierTerminalTellsANameApartFromAKeyword(): void
    {
        $semantics = new ParserSemantics(new LexicalGrammar(Factory::create(), 'pg-17.2'));

        self::assertTrue($semantics->isIdentifierTerminal('IDENT'));
        self::assertTrue($semantics->isIdentifierTerminal('users'));
        self::assertFalse($semantics->isIdentifierTerminal('SELECT'));
    }
}
