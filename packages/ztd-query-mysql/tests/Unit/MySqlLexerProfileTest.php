<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ZtdQuery\Platform\MySql\MySqlLexerProfile;
use ZtdQuery\Sql\SqlLexerProfile;
use ZtdQuery\Sql\SqlTokenKind;
use ZtdQuery\Sql\SqlTokenStream;

#[CoversClass(MySqlLexerProfile::class)]
#[UsesClass(SqlLexerProfile::class)]
#[UsesClass(SqlTokenStream::class)]
final class MySqlLexerProfileTest extends TestCase
{
    public function testAddsMySqlHashLineComments(): void
    {
        $profile = MySqlLexerProfile::create();

        self::assertTrue($profile->startsLineComment('# comment', 0));
        self::assertTrue($profile->startsLineComment('-- comment', 0));
        self::assertFalse($profile->startsLineComment('--not-comment', 0));
        self::assertSame(['/*', '*/'], $profile->blockCommentAt('/* comment */', 0));
        self::assertSame("'", $profile->stringQuoteClosing("'"));
        self::assertSame('`', $profile->identifierQuoteClosing('`'));
        self::assertSame(':', $profile->namedParameterPrefixAt(':name', 0));
        self::assertNull($profile->namedParameterPrefixAt('value::type', 6));
        self::assertTrue($profile->isBracketOpening('['));
        self::assertTrue($profile->isBracketClosing(']'));
        self::assertFalse($profile->supportsNestedBlockComments());
        self::assertFalse($profile->supportsNumberedDollarParameters());
        self::assertTrue($profile->supportsQuestionMarkParameters());
        self::assertFalse($profile->supportsNumberedQuestionMarkParameters());
        self::assertSame(4, $profile->numberLengthAt('0xCA tail', 0));
        self::assertTrue($profile->isIdentifierStart('$'));
        self::assertTrue($profile->isIdentifierPart('$'));
        self::assertTrue($profile->stringUsesBackslashEscapes("'value'", 0));
        self::assertNull($profile->dollarQuoteDelimiterAt('$tag$value$tag$', 0));
    }

    public function testUsesDefaultAndAnsiQuotesModesExplicitly(): void
    {
        $default = SqlTokenStream::tokenize('"value" `column`', MySqlLexerProfile::create())->significantTokens();
        $ansi = SqlTokenStream::tokenize('"column" `other`', MySqlLexerProfile::create(true))->significantTokens();

        self::assertSame(SqlTokenKind::String, $default[0]->kind);
        self::assertSame(SqlTokenKind::QuotedIdentifier, $default[1]->kind);
        self::assertSame(SqlTokenKind::QuotedIdentifier, $ansi[0]->kind);
        self::assertSame(SqlTokenKind::QuotedIdentifier, $ansi[1]->kind);
        self::assertSame(
            SqlTokenKind::String,
            SqlTokenStream::tokenize("'value'", MySqlLexerProfile::create())->significantTokens()[0]->kind,
        );
        self::assertSame(
            SqlTokenKind::String,
            SqlTokenStream::tokenize("'value'", MySqlLexerProfile::create(true))->significantTokens()[0]->kind,
        );
    }
}
