<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\GrammarParseException;

#[CoversClass(GrammarParseException::class)]
final class GrammarParseExceptionTest extends TestCase
{
    public function testNoRulesParsedNamesTheBisonGrammar(): void
    {
        self::assertSame(
            'No grammar rules parsed from the Bison grammar.',
            GrammarParseException::noRulesParsed('Bison')->getMessage(),
        );
    }

    public function testNoRulesParsedNamesTheLemonGrammar(): void
    {
        self::assertSame(
            'No grammar rules parsed from the Lemon grammar.',
            GrammarParseException::noRulesParsed('Lemon')->getMessage(),
        );
    }

    public function testUnreadableSourceNamesThePathThatWasAskedFor(): void
    {
        self::assertSame(
            'Failed to read: /no/such/parse.y',
            GrammarParseException::unreadableSource('/no/such/parse.y')->getMessage(),
        );
    }

    public function testUnexpectedCharacterNamesTheCharacterAndWhereItSits(): void
    {
        self::assertSame(
            "Unexpected character '#' at offset 12",
            GrammarParseException::unexpectedCharacter('#', 12)->getMessage(),
        );
    }

    public function testDanglingSlashNamesWhereTheSlashSits(): void
    {
        self::assertSame("Unexpected '/' at offset 7", GrammarParseException::danglingSlash(7)->getMessage());
    }

    public function testNamelessDirectiveNamesWhereThePercentSignSits(): void
    {
        self::assertSame("Unexpected '%' at offset 3", GrammarParseException::namelessDirective(3)->getMessage());
    }

    public function testUnterminatedTypeTagNamesWhereTheTagOpened(): void
    {
        self::assertSame(
            'Unterminated type tag starting at offset 42',
            GrammarParseException::unterminatedTypeTag(42)->getMessage(),
        );
    }
}
