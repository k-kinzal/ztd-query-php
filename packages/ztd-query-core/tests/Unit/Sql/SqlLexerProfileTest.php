<?php

declare(strict_types=1);

namespace Tests\Unit\Sql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fake\FakeSqlLexerProfiles;
use ZtdQuery\Exception\InvalidDefinitionException;
use ZtdQuery\Sql\SqlLexerProfile;

#[CoversClass(SqlLexerProfile::class)]
#[UsesClass(\ZtdQuery\Sql\Profile\SqlCommentProfile::class)]
#[UsesClass(\ZtdQuery\Sql\Profile\SqlQuoteProfile::class)]
#[UsesClass(\ZtdQuery\Sql\Profile\SqlParameterProfile::class)]
#[UsesClass(\ZtdQuery\Sql\Profile\SqlSymbolProfile::class)]
#[UsesClass(\ZtdQuery\Sql\LexicalDelimiters::class)]
#[UsesClass(\ZtdQuery\Sql\LexicalPattern::class)]
final class SqlLexerProfileTest extends TestCase
{
    public function testStartsLineCommentSeesEveryPrefixTheDialectDeclares(): void
    {
        $profile = FakeSqlLexerProfiles::custom(
            lineCommentPrefixes: ['--', '#'],
            whitespaceDelimitedLineCommentPrefixes: ['//'],
            blockCommentPairs: ['/*' => '*/'],
            stringQuotePairs: ["'" => "'"],
            identifierQuotePairs: ['"' => '"', '[' => ']'],
            namedParameterSeparators: [':' => ['::'], '$' => []],
            namedParameterSuffixPatterns: [':' => '/^<[^ ]*>/', '$' => '/^\([^ ]*\)/'],
            namedParameterForbiddenPredecessors: [':' => [':'], '$' => []],
            backslashEscapedStringPrefixes: ['E'],
            positionalParameterPatterns: ['/^\$[0-9]+/', '/^\?[0-9]*/'],
            dollarQuoteDelimiterPattern: '/^\$(?:[_A-Za-z][_A-Za-z0-9]*)?\$/',
            numericLiteralPattern: '/^(?:0[xX][0-9A-Fa-f](?:_?[0-9A-Fa-f])*|[0-9]+(?:_[0-9]+)*)/',
            identifierStartPattern: '/^[_A-Za-z]$/',
            identifierPartPattern: '/^[_A-Za-z0-9$]$/',
            bracketPair: ['[', ']'],
            nestingPair: ['(', ')'],
            statementDelimiter: ';',
            listDelimiter: ',',
            nestedBlockComments: true,
            backslashEscapedStrings: false,
        );

        self::assertTrue($profile->startsLineComment('-- comment', 0));
        self::assertTrue($profile->startsLineComment('x # comment', 2));
        self::assertTrue($profile->startsLineComment('// comment', 0));
        self::assertFalse($profile->startsLineComment('//not-comment', 0));
    }

    public function testBlockCommentAtAnswersTheDelimitersThatOpenAndCloseOne(): void
    {
        $profile = FakeSqlLexerProfiles::custom(
            lineCommentPrefixes: ['--', '#'],
            whitespaceDelimitedLineCommentPrefixes: ['//'],
            blockCommentPairs: ['/*' => '*/'],
            stringQuotePairs: ["'" => "'"],
            identifierQuotePairs: ['"' => '"', '[' => ']'],
            namedParameterSeparators: [':' => ['::'], '$' => []],
            namedParameterSuffixPatterns: [':' => '/^<[^ ]*>/', '$' => '/^\([^ ]*\)/'],
            namedParameterForbiddenPredecessors: [':' => [':'], '$' => []],
            backslashEscapedStringPrefixes: ['E'],
            positionalParameterPatterns: ['/^\$[0-9]+/', '/^\?[0-9]*/'],
            dollarQuoteDelimiterPattern: '/^\$(?:[_A-Za-z][_A-Za-z0-9]*)?\$/',
            numericLiteralPattern: '/^(?:0[xX][0-9A-Fa-f](?:_?[0-9A-Fa-f])*|[0-9]+(?:_[0-9]+)*)/',
            identifierStartPattern: '/^[_A-Za-z]$/',
            identifierPartPattern: '/^[_A-Za-z0-9$]$/',
            bracketPair: ['[', ']'],
            nestingPair: ['(', ')'],
            statementDelimiter: ';',
            listDelimiter: ',',
            nestedBlockComments: true,
            backslashEscapedStrings: false,
        );

        self::assertSame(['/*', '*/'], $profile->blockCommentAt('/* comment */', 0));
        self::assertNull($profile->blockCommentAt('/ value', 0));
    }

    public function testStringQuoteClosingAnswersWhatClosesAStringThisOpened(): void
    {
        $profile = FakeSqlLexerProfiles::custom(
            lineCommentPrefixes: ['--', '#'],
            whitespaceDelimitedLineCommentPrefixes: ['//'],
            blockCommentPairs: ['/*' => '*/'],
            stringQuotePairs: ["'" => "'"],
            identifierQuotePairs: ['"' => '"', '[' => ']'],
            namedParameterSeparators: [':' => ['::'], '$' => []],
            namedParameterSuffixPatterns: [':' => '/^<[^ ]*>/', '$' => '/^\([^ ]*\)/'],
            namedParameterForbiddenPredecessors: [':' => [':'], '$' => []],
            backslashEscapedStringPrefixes: ['E'],
            positionalParameterPatterns: ['/^\$[0-9]+/', '/^\?[0-9]*/'],
            dollarQuoteDelimiterPattern: '/^\$(?:[_A-Za-z][_A-Za-z0-9]*)?\$/',
            numericLiteralPattern: '/^(?:0[xX][0-9A-Fa-f](?:_?[0-9A-Fa-f])*|[0-9]+(?:_[0-9]+)*)/',
            identifierStartPattern: '/^[_A-Za-z]$/',
            identifierPartPattern: '/^[_A-Za-z0-9$]$/',
            bracketPair: ['[', ']'],
            nestingPair: ['(', ')'],
            statementDelimiter: ';',
            listDelimiter: ',',
            nestedBlockComments: true,
            backslashEscapedStrings: false,
        );

        self::assertSame("'", $profile->stringQuoteClosing("'"));
    }

    public function testIdentifierQuoteClosingAnswersWhatClosesAnIdentifierThisOpened(): void
    {
        $profile = FakeSqlLexerProfiles::custom(
            lineCommentPrefixes: ['--', '#'],
            whitespaceDelimitedLineCommentPrefixes: ['//'],
            blockCommentPairs: ['/*' => '*/'],
            stringQuotePairs: ["'" => "'"],
            identifierQuotePairs: ['"' => '"', '[' => ']'],
            namedParameterSeparators: [':' => ['::'], '$' => []],
            namedParameterSuffixPatterns: [':' => '/^<[^ ]*>/', '$' => '/^\([^ ]*\)/'],
            namedParameterForbiddenPredecessors: [':' => [':'], '$' => []],
            backslashEscapedStringPrefixes: ['E'],
            positionalParameterPatterns: ['/^\$[0-9]+/', '/^\?[0-9]*/'],
            dollarQuoteDelimiterPattern: '/^\$(?:[_A-Za-z][_A-Za-z0-9]*)?\$/',
            numericLiteralPattern: '/^(?:0[xX][0-9A-Fa-f](?:_?[0-9A-Fa-f])*|[0-9]+(?:_[0-9]+)*)/',
            identifierStartPattern: '/^[_A-Za-z]$/',
            identifierPartPattern: '/^[_A-Za-z0-9$]$/',
            bracketPair: ['[', ']'],
            nestingPair: ['(', ')'],
            statementDelimiter: ';',
            listDelimiter: ',',
            nestedBlockComments: true,
            backslashEscapedStrings: false,
        );

        self::assertSame(']', $profile->identifierQuoteClosing('['));
        self::assertNull($profile->identifierQuoteClosing('`'));
    }

    public function testUnquoteIdentifierReadsTheNameAQuotedIdentifierStandsFor(): void
    {
        $profile = FakeSqlLexerProfiles::custom(
            lineCommentPrefixes: ['--', '#'],
            whitespaceDelimitedLineCommentPrefixes: ['//'],
            blockCommentPairs: ['/*' => '*/'],
            stringQuotePairs: ["'" => "'"],
            identifierQuotePairs: ['"' => '"', '[' => ']'],
            namedParameterSeparators: [':' => ['::'], '$' => []],
            namedParameterSuffixPatterns: [':' => '/^<[^ ]*>/', '$' => '/^\([^ ]*\)/'],
            namedParameterForbiddenPredecessors: [':' => [':'], '$' => []],
            backslashEscapedStringPrefixes: ['E'],
            positionalParameterPatterns: ['/^\$[0-9]+/', '/^\?[0-9]*/'],
            dollarQuoteDelimiterPattern: '/^\$(?:[_A-Za-z][_A-Za-z0-9]*)?\$/',
            numericLiteralPattern: '/^(?:0[xX][0-9A-Fa-f](?:_?[0-9A-Fa-f])*|[0-9]+(?:_[0-9]+)*)/',
            identifierStartPattern: '/^[_A-Za-z]$/',
            identifierPartPattern: '/^[_A-Za-z0-9$]$/',
            bracketPair: ['[', ']'],
            nestingPair: ['(', ')'],
            statementDelimiter: ';',
            listDelimiter: ',',
            nestedBlockComments: true,
            backslashEscapedStrings: false,
        );

        self::assertSame('bracket]name', $profile->unquoteIdentifier('[bracket]]name]'));
        self::assertSame('[bracket', $profile->unquoteIdentifier('[bracket'));
        self::assertSame('bracket]', $profile->unquoteIdentifier('bracket]'));
    }

    public function testQuotedIdentifierValueSpeaksOnlyForACompleteQuotedIdentifier(): void
    {
        $profile = FakeSqlLexerProfiles::custom(
            lineCommentPrefixes: ['--', '#'],
            whitespaceDelimitedLineCommentPrefixes: ['//'],
            blockCommentPairs: ['/*' => '*/'],
            stringQuotePairs: ["'" => "'"],
            identifierQuotePairs: ['"' => '"', '[' => ']'],
            namedParameterSeparators: [':' => ['::'], '$' => []],
            namedParameterSuffixPatterns: [':' => '/^<[^ ]*>/', '$' => '/^\([^ ]*\)/'],
            namedParameterForbiddenPredecessors: [':' => [':'], '$' => []],
            backslashEscapedStringPrefixes: ['E'],
            positionalParameterPatterns: ['/^\$[0-9]+/', '/^\?[0-9]*/'],
            dollarQuoteDelimiterPattern: '/^\$(?:[_A-Za-z][_A-Za-z0-9]*)?\$/',
            numericLiteralPattern: '/^(?:0[xX][0-9A-Fa-f](?:_?[0-9A-Fa-f])*|[0-9]+(?:_[0-9]+)*)/',
            identifierStartPattern: '/^[_A-Za-z]$/',
            identifierPartPattern: '/^[_A-Za-z0-9$]$/',
            bracketPair: ['[', ']'],
            nestingPair: ['(', ')'],
            statementDelimiter: ';',
            listDelimiter: ',',
            nestedBlockComments: true,
            backslashEscapedStrings: false,
        );

        self::assertSame('bracket]name', $profile->quotedIdentifierValue('[bracket]]name]'));
        self::assertNull($profile->quotedIdentifierValue('[bracket'));
        self::assertNull($profile->quotedIdentifierValue('[]'));
        self::assertNull($profile->quotedIdentifierValue('plain'));
    }

    public function testSupportsNestedBlockCommentsFollowsTheDialect(): void
    {
        $profile = FakeSqlLexerProfiles::custom(
            lineCommentPrefixes: ['--', '#'],
            whitespaceDelimitedLineCommentPrefixes: ['//'],
            blockCommentPairs: ['/*' => '*/'],
            stringQuotePairs: ["'" => "'"],
            identifierQuotePairs: ['"' => '"', '[' => ']'],
            namedParameterSeparators: [':' => ['::'], '$' => []],
            namedParameterSuffixPatterns: [':' => '/^<[^ ]*>/', '$' => '/^\([^ ]*\)/'],
            namedParameterForbiddenPredecessors: [':' => [':'], '$' => []],
            backslashEscapedStringPrefixes: ['E'],
            positionalParameterPatterns: ['/^\$[0-9]+/', '/^\?[0-9]*/'],
            dollarQuoteDelimiterPattern: '/^\$(?:[_A-Za-z][_A-Za-z0-9]*)?\$/',
            numericLiteralPattern: '/^(?:0[xX][0-9A-Fa-f](?:_?[0-9A-Fa-f])*|[0-9]+(?:_[0-9]+)*)/',
            identifierStartPattern: '/^[_A-Za-z]$/',
            identifierPartPattern: '/^[_A-Za-z0-9$]$/',
            bracketPair: ['[', ']'],
            nestingPair: ['(', ')'],
            statementDelimiter: ';',
            listDelimiter: ',',
            nestedBlockComments: true,
            backslashEscapedStrings: false,
        );

        self::assertTrue($profile->supportsNestedBlockComments());
    }

    public function testDollarQuoteDelimiterAtAnswersTheDelimiterStartingThere(): void
    {
        $profile = FakeSqlLexerProfiles::custom(
            lineCommentPrefixes: ['--', '#'],
            whitespaceDelimitedLineCommentPrefixes: ['//'],
            blockCommentPairs: ['/*' => '*/'],
            stringQuotePairs: ["'" => "'"],
            identifierQuotePairs: ['"' => '"', '[' => ']'],
            namedParameterSeparators: [':' => ['::'], '$' => []],
            namedParameterSuffixPatterns: [':' => '/^<[^ ]*>/', '$' => '/^\([^ ]*\)/'],
            namedParameterForbiddenPredecessors: [':' => [':'], '$' => []],
            backslashEscapedStringPrefixes: ['E'],
            positionalParameterPatterns: ['/^\$[0-9]+/', '/^\?[0-9]*/'],
            dollarQuoteDelimiterPattern: '/^\$(?:[_A-Za-z][_A-Za-z0-9]*)?\$/',
            numericLiteralPattern: '/^(?:0[xX][0-9A-Fa-f](?:_?[0-9A-Fa-f])*|[0-9]+(?:_[0-9]+)*)/',
            identifierStartPattern: '/^[_A-Za-z]$/',
            identifierPartPattern: '/^[_A-Za-z0-9$]$/',
            bracketPair: ['[', ']'],
            nestingPair: ['(', ')'],
            statementDelimiter: ';',
            listDelimiter: ',',
            nestedBlockComments: true,
            backslashEscapedStrings: false,
        );

        self::assertSame('$tag$', $profile->dollarQuoteDelimiterAt('$tag$body$tag$', 0));
    }

    public function testPositionalParameterLengthAtMeasuresTheParameterStartingThere(): void
    {
        $profile = FakeSqlLexerProfiles::custom(
            lineCommentPrefixes: ['--', '#'],
            whitespaceDelimitedLineCommentPrefixes: ['//'],
            blockCommentPairs: ['/*' => '*/'],
            stringQuotePairs: ["'" => "'"],
            identifierQuotePairs: ['"' => '"', '[' => ']'],
            namedParameterSeparators: [':' => ['::'], '$' => []],
            namedParameterSuffixPatterns: [':' => '/^<[^ ]*>/', '$' => '/^\([^ ]*\)/'],
            namedParameterForbiddenPredecessors: [':' => [':'], '$' => []],
            backslashEscapedStringPrefixes: ['E'],
            positionalParameterPatterns: ['/^\$[0-9]+/', '/^\?[0-9]*/'],
            dollarQuoteDelimiterPattern: '/^\$(?:[_A-Za-z][_A-Za-z0-9]*)?\$/',
            numericLiteralPattern: '/^(?:0[xX][0-9A-Fa-f](?:_?[0-9A-Fa-f])*|[0-9]+(?:_[0-9]+)*)/',
            identifierStartPattern: '/^[_A-Za-z]$/',
            identifierPartPattern: '/^[_A-Za-z0-9$]$/',
            bracketPair: ['[', ']'],
            nestingPair: ['(', ')'],
            statementDelimiter: ';',
            listDelimiter: ',',
            nestedBlockComments: true,
            backslashEscapedStrings: false,
        );

        self::assertSame(3, $profile->positionalParameterLengthAt('$12 tail', 0));
        self::assertSame(3, $profile->positionalParameterLengthAt('?34 tail', 0));
        self::assertSame(0, $profile->positionalParameterLengthAt('value', 0));
    }

    public function testNamedParameterPrefixAtTellsAParameterFromAnOperatorSpelledAlike(): void
    {
        $profile = FakeSqlLexerProfiles::custom(
            lineCommentPrefixes: ['--', '#'],
            whitespaceDelimitedLineCommentPrefixes: ['//'],
            blockCommentPairs: ['/*' => '*/'],
            stringQuotePairs: ["'" => "'"],
            identifierQuotePairs: ['"' => '"', '[' => ']'],
            namedParameterSeparators: [':' => ['::'], '$' => []],
            namedParameterSuffixPatterns: [':' => '/^<[^ ]*>/', '$' => '/^\([^ ]*\)/'],
            namedParameterForbiddenPredecessors: [':' => [':'], '$' => []],
            backslashEscapedStringPrefixes: ['E'],
            positionalParameterPatterns: ['/^\$[0-9]+/', '/^\?[0-9]*/'],
            dollarQuoteDelimiterPattern: '/^\$(?:[_A-Za-z][_A-Za-z0-9]*)?\$/',
            numericLiteralPattern: '/^(?:0[xX][0-9A-Fa-f](?:_?[0-9A-Fa-f])*|[0-9]+(?:_[0-9]+)*)/',
            identifierStartPattern: '/^[_A-Za-z]$/',
            identifierPartPattern: '/^[_A-Za-z0-9$]$/',
            bracketPair: ['[', ']'],
            nestingPair: ['(', ')'],
            statementDelimiter: ';',
            listDelimiter: ',',
            nestedBlockComments: true,
            backslashEscapedStrings: false,
        );

        self::assertSame(':', $profile->namedParameterPrefixAt(':name', 0));
        self::assertSame(':', $profile->namedParameterPrefixAt('x:name', 1));
        self::assertSame('$', $profile->namedParameterPrefixAt('$name', 0));
        self::assertNull($profile->namedParameterPrefixAt('value::type', 6));
        self::assertNull($profile->namedParameterPrefixAt('::name', 1));
    }

    public function testParameterNameSeparatorAtAnswersWhatSeparatesAPrefixFromItsName(): void
    {
        $profile = FakeSqlLexerProfiles::custom(
            lineCommentPrefixes: ['--', '#'],
            whitespaceDelimitedLineCommentPrefixes: ['//'],
            blockCommentPairs: ['/*' => '*/'],
            stringQuotePairs: ["'" => "'"],
            identifierQuotePairs: ['"' => '"', '[' => ']'],
            namedParameterSeparators: [':' => ['::'], '$' => []],
            namedParameterSuffixPatterns: [':' => '/^<[^ ]*>/', '$' => '/^\([^ ]*\)/'],
            namedParameterForbiddenPredecessors: [':' => [':'], '$' => []],
            backslashEscapedStringPrefixes: ['E'],
            positionalParameterPatterns: ['/^\$[0-9]+/', '/^\?[0-9]*/'],
            dollarQuoteDelimiterPattern: '/^\$(?:[_A-Za-z][_A-Za-z0-9]*)?\$/',
            numericLiteralPattern: '/^(?:0[xX][0-9A-Fa-f](?:_?[0-9A-Fa-f])*|[0-9]+(?:_[0-9]+)*)/',
            identifierStartPattern: '/^[_A-Za-z]$/',
            identifierPartPattern: '/^[_A-Za-z0-9$]$/',
            bracketPair: ['[', ']'],
            nestingPair: ['(', ')'],
            statementDelimiter: ';',
            listDelimiter: ',',
            nestedBlockComments: true,
            backslashEscapedStrings: false,
        );

        self::assertSame('::', $profile->parameterNameSeparatorAt(':', 'name::part', 4));
        self::assertNull($profile->parameterNameSeparatorAt(':', 'name.part', 4));
    }

    public function testParameterSuffixLengthMeasuresWhatFollowsAParameterName(): void
    {
        $profile = FakeSqlLexerProfiles::custom(
            lineCommentPrefixes: ['--', '#'],
            whitespaceDelimitedLineCommentPrefixes: ['//'],
            blockCommentPairs: ['/*' => '*/'],
            stringQuotePairs: ["'" => "'"],
            identifierQuotePairs: ['"' => '"', '[' => ']'],
            namedParameterSeparators: [':' => ['::'], '$' => []],
            namedParameterSuffixPatterns: [':' => '/^<[^ ]*>/', '$' => '/^\([^ ]*\)/'],
            namedParameterForbiddenPredecessors: [':' => [':'], '$' => []],
            backslashEscapedStringPrefixes: ['E'],
            positionalParameterPatterns: ['/^\$[0-9]+/', '/^\?[0-9]*/'],
            dollarQuoteDelimiterPattern: '/^\$(?:[_A-Za-z][_A-Za-z0-9]*)?\$/',
            numericLiteralPattern: '/^(?:0[xX][0-9A-Fa-f](?:_?[0-9A-Fa-f])*|[0-9]+(?:_[0-9]+)*)/',
            identifierStartPattern: '/^[_A-Za-z]$/',
            identifierPartPattern: '/^[_A-Za-z0-9$]$/',
            bracketPair: ['[', ']'],
            nestingPair: ['(', ')'],
            statementDelimiter: ';',
            listDelimiter: ',',
            nestedBlockComments: true,
            backslashEscapedStrings: false,
        );

        self::assertSame(9, $profile->parameterSuffixLength('$', '(payload)', 0));
        self::assertSame(0, $profile->parameterSuffixLength('$', 'payload', 0));
    }

    public function testStringUsesBackslashEscapesLooksAtWhatIntroducedTheString(): void
    {
        $profile = FakeSqlLexerProfiles::custom(
            lineCommentPrefixes: ['--', '#'],
            whitespaceDelimitedLineCommentPrefixes: ['//'],
            blockCommentPairs: ['/*' => '*/'],
            stringQuotePairs: ["'" => "'"],
            identifierQuotePairs: ['"' => '"', '[' => ']'],
            namedParameterSeparators: [':' => ['::'], '$' => []],
            namedParameterSuffixPatterns: [':' => '/^<[^ ]*>/', '$' => '/^\([^ ]*\)/'],
            namedParameterForbiddenPredecessors: [':' => [':'], '$' => []],
            backslashEscapedStringPrefixes: ['E'],
            positionalParameterPatterns: ['/^\$[0-9]+/', '/^\?[0-9]*/'],
            dollarQuoteDelimiterPattern: '/^\$(?:[_A-Za-z][_A-Za-z0-9]*)?\$/',
            numericLiteralPattern: '/^(?:0[xX][0-9A-Fa-f](?:_?[0-9A-Fa-f])*|[0-9]+(?:_[0-9]+)*)/',
            identifierStartPattern: '/^[_A-Za-z]$/',
            identifierPartPattern: '/^[_A-Za-z0-9$]$/',
            bracketPair: ['[', ']'],
            nestingPair: ['(', ')'],
            statementDelimiter: ';',
            listDelimiter: ',',
            nestedBlockComments: true,
            backslashEscapedStrings: false,
        );

        self::assertTrue($profile->stringUsesBackslashEscapes("E'value'", 1));
        self::assertTrue($profile->stringUsesBackslashEscapes("E'value", 1));
        self::assertTrue($profile->stringUsesBackslashEscapes("+E'value'", 2));
        self::assertTrue($profile->stringUsesBackslashEscapes("a+E'value'", 3));
        self::assertFalse($profile->stringUsesBackslashEscapes("nameE'value'", 5));
    }

    public function testNumberLengthAtMeasuresTheNumberStartingThere(): void
    {
        $profile = FakeSqlLexerProfiles::custom(
            lineCommentPrefixes: ['--', '#'],
            whitespaceDelimitedLineCommentPrefixes: ['//'],
            blockCommentPairs: ['/*' => '*/'],
            stringQuotePairs: ["'" => "'"],
            identifierQuotePairs: ['"' => '"', '[' => ']'],
            namedParameterSeparators: [':' => ['::'], '$' => []],
            namedParameterSuffixPatterns: [':' => '/^<[^ ]*>/', '$' => '/^\([^ ]*\)/'],
            namedParameterForbiddenPredecessors: [':' => [':'], '$' => []],
            backslashEscapedStringPrefixes: ['E'],
            positionalParameterPatterns: ['/^\$[0-9]+/', '/^\?[0-9]*/'],
            dollarQuoteDelimiterPattern: '/^\$(?:[_A-Za-z][_A-Za-z0-9]*)?\$/',
            numericLiteralPattern: '/^(?:0[xX][0-9A-Fa-f](?:_?[0-9A-Fa-f])*|[0-9]+(?:_[0-9]+)*)/',
            identifierStartPattern: '/^[_A-Za-z]$/',
            identifierPartPattern: '/^[_A-Za-z0-9$]$/',
            bracketPair: ['[', ']'],
            nestingPair: ['(', ')'],
            statementDelimiter: ';',
            listDelimiter: ',',
            nestedBlockComments: true,
            backslashEscapedStrings: false,
        );

        self::assertSame(7, $profile->numberLengthAt('0xCA_FE tail', 0));
        self::assertSame(2, $profile->numberLengthAt('x42 tail', 1));
        self::assertSame(0, $profile->numberLengthAt('value', 0));
    }

    public function testIsIdentifierStartAnswersForTheFirstCharacterOfAName(): void
    {
        $profile = FakeSqlLexerProfiles::custom(
            lineCommentPrefixes: ['--', '#'],
            whitespaceDelimitedLineCommentPrefixes: ['//'],
            blockCommentPairs: ['/*' => '*/'],
            stringQuotePairs: ["'" => "'"],
            identifierQuotePairs: ['"' => '"', '[' => ']'],
            namedParameterSeparators: [':' => ['::'], '$' => []],
            namedParameterSuffixPatterns: [':' => '/^<[^ ]*>/', '$' => '/^\([^ ]*\)/'],
            namedParameterForbiddenPredecessors: [':' => [':'], '$' => []],
            backslashEscapedStringPrefixes: ['E'],
            positionalParameterPatterns: ['/^\$[0-9]+/', '/^\?[0-9]*/'],
            dollarQuoteDelimiterPattern: '/^\$(?:[_A-Za-z][_A-Za-z0-9]*)?\$/',
            numericLiteralPattern: '/^(?:0[xX][0-9A-Fa-f](?:_?[0-9A-Fa-f])*|[0-9]+(?:_[0-9]+)*)/',
            identifierStartPattern: '/^[_A-Za-z]$/',
            identifierPartPattern: '/^[_A-Za-z0-9$]$/',
            bracketPair: ['[', ']'],
            nestingPair: ['(', ')'],
            statementDelimiter: ';',
            listDelimiter: ',',
            nestedBlockComments: true,
            backslashEscapedStrings: false,
        );

        self::assertTrue($profile->isIdentifierStart('_'));
        self::assertFalse($profile->isIdentifierStart('1'));
    }

    public function testIsIdentifierPartAnswersForEveryLaterCharacterOfAName(): void
    {
        $profile = FakeSqlLexerProfiles::custom(
            lineCommentPrefixes: ['--', '#'],
            whitespaceDelimitedLineCommentPrefixes: ['//'],
            blockCommentPairs: ['/*' => '*/'],
            stringQuotePairs: ["'" => "'"],
            identifierQuotePairs: ['"' => '"', '[' => ']'],
            namedParameterSeparators: [':' => ['::'], '$' => []],
            namedParameterSuffixPatterns: [':' => '/^<[^ ]*>/', '$' => '/^\([^ ]*\)/'],
            namedParameterForbiddenPredecessors: [':' => [':'], '$' => []],
            backslashEscapedStringPrefixes: ['E'],
            positionalParameterPatterns: ['/^\$[0-9]+/', '/^\?[0-9]*/'],
            dollarQuoteDelimiterPattern: '/^\$(?:[_A-Za-z][_A-Za-z0-9]*)?\$/',
            numericLiteralPattern: '/^(?:0[xX][0-9A-Fa-f](?:_?[0-9A-Fa-f])*|[0-9]+(?:_[0-9]+)*)/',
            identifierStartPattern: '/^[_A-Za-z]$/',
            identifierPartPattern: '/^[_A-Za-z0-9$]$/',
            bracketPair: ['[', ']'],
            nestingPair: ['(', ')'],
            statementDelimiter: ';',
            listDelimiter: ',',
            nestedBlockComments: true,
            backslashEscapedStrings: false,
        );

        self::assertTrue($profile->isIdentifierPart('$'));
    }

    public function testIsBracketOpeningAnswersForTheCharacterThatOpensOne(): void
    {
        $profile = FakeSqlLexerProfiles::custom(
            lineCommentPrefixes: ['--', '#'],
            whitespaceDelimitedLineCommentPrefixes: ['//'],
            blockCommentPairs: ['/*' => '*/'],
            stringQuotePairs: ["'" => "'"],
            identifierQuotePairs: ['"' => '"', '[' => ']'],
            namedParameterSeparators: [':' => ['::'], '$' => []],
            namedParameterSuffixPatterns: [':' => '/^<[^ ]*>/', '$' => '/^\([^ ]*\)/'],
            namedParameterForbiddenPredecessors: [':' => [':'], '$' => []],
            backslashEscapedStringPrefixes: ['E'],
            positionalParameterPatterns: ['/^\$[0-9]+/', '/^\?[0-9]*/'],
            dollarQuoteDelimiterPattern: '/^\$(?:[_A-Za-z][_A-Za-z0-9]*)?\$/',
            numericLiteralPattern: '/^(?:0[xX][0-9A-Fa-f](?:_?[0-9A-Fa-f])*|[0-9]+(?:_[0-9]+)*)/',
            identifierStartPattern: '/^[_A-Za-z]$/',
            identifierPartPattern: '/^[_A-Za-z0-9$]$/',
            bracketPair: ['[', ']'],
            nestingPair: ['(', ')'],
            statementDelimiter: ';',
            listDelimiter: ',',
            nestedBlockComments: true,
            backslashEscapedStrings: false,
        );

        self::assertTrue($profile->isBracketOpening('['));
        self::assertFalse($profile->isBracketOpening('('));
    }

    public function testIsBracketClosingAnswersForTheCharacterThatClosesOne(): void
    {
        $profile = FakeSqlLexerProfiles::custom(
            lineCommentPrefixes: ['--', '#'],
            whitespaceDelimitedLineCommentPrefixes: ['//'],
            blockCommentPairs: ['/*' => '*/'],
            stringQuotePairs: ["'" => "'"],
            identifierQuotePairs: ['"' => '"', '[' => ']'],
            namedParameterSeparators: [':' => ['::'], '$' => []],
            namedParameterSuffixPatterns: [':' => '/^<[^ ]*>/', '$' => '/^\([^ ]*\)/'],
            namedParameterForbiddenPredecessors: [':' => [':'], '$' => []],
            backslashEscapedStringPrefixes: ['E'],
            positionalParameterPatterns: ['/^\$[0-9]+/', '/^\?[0-9]*/'],
            dollarQuoteDelimiterPattern: '/^\$(?:[_A-Za-z][_A-Za-z0-9]*)?\$/',
            numericLiteralPattern: '/^(?:0[xX][0-9A-Fa-f](?:_?[0-9A-Fa-f])*|[0-9]+(?:_[0-9]+)*)/',
            identifierStartPattern: '/^[_A-Za-z]$/',
            identifierPartPattern: '/^[_A-Za-z0-9$]$/',
            bracketPair: ['[', ']'],
            nestingPair: ['(', ')'],
            statementDelimiter: ';',
            listDelimiter: ',',
            nestedBlockComments: true,
            backslashEscapedStrings: false,
        );

        self::assertTrue($profile->isBracketClosing(']'));
        self::assertFalse($profile->isBracketClosing(')'));
    }

    public function testIsNestingOpeningAnswersForTheCharacterThatOpensOne(): void
    {
        $profile = FakeSqlLexerProfiles::custom(
            lineCommentPrefixes: ['--', '#'],
            whitespaceDelimitedLineCommentPrefixes: ['//'],
            blockCommentPairs: ['/*' => '*/'],
            stringQuotePairs: ["'" => "'"],
            identifierQuotePairs: ['"' => '"', '[' => ']'],
            namedParameterSeparators: [':' => ['::'], '$' => []],
            namedParameterSuffixPatterns: [':' => '/^<[^ ]*>/', '$' => '/^\([^ ]*\)/'],
            namedParameterForbiddenPredecessors: [':' => [':'], '$' => []],
            backslashEscapedStringPrefixes: ['E'],
            positionalParameterPatterns: ['/^\$[0-9]+/', '/^\?[0-9]*/'],
            dollarQuoteDelimiterPattern: '/^\$(?:[_A-Za-z][_A-Za-z0-9]*)?\$/',
            numericLiteralPattern: '/^(?:0[xX][0-9A-Fa-f](?:_?[0-9A-Fa-f])*|[0-9]+(?:_[0-9]+)*)/',
            identifierStartPattern: '/^[_A-Za-z]$/',
            identifierPartPattern: '/^[_A-Za-z0-9$]$/',
            bracketPair: ['[', ']'],
            nestingPair: ['(', ')'],
            statementDelimiter: ';',
            listDelimiter: ',',
            nestedBlockComments: true,
            backslashEscapedStrings: false,
        );

        self::assertTrue($profile->isNestingOpening('('));
    }

    public function testIsNestingClosingAnswersForTheCharacterThatClosesOne(): void
    {
        $profile = FakeSqlLexerProfiles::custom(
            lineCommentPrefixes: ['--', '#'],
            whitespaceDelimitedLineCommentPrefixes: ['//'],
            blockCommentPairs: ['/*' => '*/'],
            stringQuotePairs: ["'" => "'"],
            identifierQuotePairs: ['"' => '"', '[' => ']'],
            namedParameterSeparators: [':' => ['::'], '$' => []],
            namedParameterSuffixPatterns: [':' => '/^<[^ ]*>/', '$' => '/^\([^ ]*\)/'],
            namedParameterForbiddenPredecessors: [':' => [':'], '$' => []],
            backslashEscapedStringPrefixes: ['E'],
            positionalParameterPatterns: ['/^\$[0-9]+/', '/^\?[0-9]*/'],
            dollarQuoteDelimiterPattern: '/^\$(?:[_A-Za-z][_A-Za-z0-9]*)?\$/',
            numericLiteralPattern: '/^(?:0[xX][0-9A-Fa-f](?:_?[0-9A-Fa-f])*|[0-9]+(?:_[0-9]+)*)/',
            identifierStartPattern: '/^[_A-Za-z]$/',
            identifierPartPattern: '/^[_A-Za-z0-9$]$/',
            bracketPair: ['[', ']'],
            nestingPair: ['(', ')'],
            statementDelimiter: ';',
            listDelimiter: ',',
            nestedBlockComments: true,
            backslashEscapedStrings: false,
        );

        self::assertTrue($profile->isNestingClosing(')'));
    }

    public function testIsStatementDelimiterAnswersForTheSymbolThatEndsAStatement(): void
    {
        $profile = FakeSqlLexerProfiles::custom(
            lineCommentPrefixes: ['--', '#'],
            whitespaceDelimitedLineCommentPrefixes: ['//'],
            blockCommentPairs: ['/*' => '*/'],
            stringQuotePairs: ["'" => "'"],
            identifierQuotePairs: ['"' => '"', '[' => ']'],
            namedParameterSeparators: [':' => ['::'], '$' => []],
            namedParameterSuffixPatterns: [':' => '/^<[^ ]*>/', '$' => '/^\([^ ]*\)/'],
            namedParameterForbiddenPredecessors: [':' => [':'], '$' => []],
            backslashEscapedStringPrefixes: ['E'],
            positionalParameterPatterns: ['/^\$[0-9]+/', '/^\?[0-9]*/'],
            dollarQuoteDelimiterPattern: '/^\$(?:[_A-Za-z][_A-Za-z0-9]*)?\$/',
            numericLiteralPattern: '/^(?:0[xX][0-9A-Fa-f](?:_?[0-9A-Fa-f])*|[0-9]+(?:_[0-9]+)*)/',
            identifierStartPattern: '/^[_A-Za-z]$/',
            identifierPartPattern: '/^[_A-Za-z0-9$]$/',
            bracketPair: ['[', ']'],
            nestingPair: ['(', ')'],
            statementDelimiter: ';',
            listDelimiter: ',',
            nestedBlockComments: true,
            backslashEscapedStrings: false,
        );

        self::assertTrue($profile->isStatementDelimiter(';'));
    }

    public function testListDelimiterAnswersTheSeparatorBetweenListItems(): void
    {
        $profile = FakeSqlLexerProfiles::custom(
            lineCommentPrefixes: ['--', '#'],
            whitespaceDelimitedLineCommentPrefixes: ['//'],
            blockCommentPairs: ['/*' => '*/'],
            stringQuotePairs: ["'" => "'"],
            identifierQuotePairs: ['"' => '"', '[' => ']'],
            namedParameterSeparators: [':' => ['::'], '$' => []],
            namedParameterSuffixPatterns: [':' => '/^<[^ ]*>/', '$' => '/^\([^ ]*\)/'],
            namedParameterForbiddenPredecessors: [':' => [':'], '$' => []],
            backslashEscapedStringPrefixes: ['E'],
            positionalParameterPatterns: ['/^\$[0-9]+/', '/^\?[0-9]*/'],
            dollarQuoteDelimiterPattern: '/^\$(?:[_A-Za-z][_A-Za-z0-9]*)?\$/',
            numericLiteralPattern: '/^(?:0[xX][0-9A-Fa-f](?:_?[0-9A-Fa-f])*|[0-9]+(?:_[0-9]+)*)/',
            identifierStartPattern: '/^[_A-Za-z]$/',
            identifierPartPattern: '/^[_A-Za-z0-9$]$/',
            bracketPair: ['[', ']'],
            nestingPair: ['(', ')'],
            statementDelimiter: ';',
            listDelimiter: ',',
            nestedBlockComments: true,
            backslashEscapedStrings: false,
        );

        self::assertSame(',', $profile->listDelimiter());
    }

    public function testRejectsEmptyLexicalDelimiter(): void
    {
        $this->expectException(InvalidDefinitionException::class);

        FakeSqlLexerProfiles::custom(lineCommentPrefixes: ['']);
    }

    public function testRejectsEmptyIdentifierQuoteDelimiter(): void
    {
        $this->expectException(InvalidDefinitionException::class);
        $this->expectExceptionMessage('Identifier quote delimiters must not be empty.');

        FakeSqlLexerProfiles::custom(identifierQuotePairs: ['' => '"']);
    }

    public function testRejectsInvalidDollarQuotePattern(): void
    {
        $this->expectException(InvalidDefinitionException::class);

        FakeSqlLexerProfiles::custom(dollarQuoteDelimiterPattern: '/[/');
    }

    public function testRejectsInvalidIdentifierStartPattern(): void
    {
        $this->expectException(InvalidDefinitionException::class);

        FakeSqlLexerProfiles::custom(identifierStartPattern: '/[/');
    }

    public function testRejectsInvalidIdentifierPartPattern(): void
    {
        $this->expectException(InvalidDefinitionException::class);

        FakeSqlLexerProfiles::custom(identifierPartPattern: '/[/');
    }

    public function testRejectsEmptyOpeningBracketDelimiter(): void
    {
        $this->expectException(InvalidDefinitionException::class);

        FakeSqlLexerProfiles::custom(bracketPair: ['', ']']);
    }

    public function testRejectsEmptyClosingBracketDelimiter(): void
    {
        $this->expectException(InvalidDefinitionException::class);

        FakeSqlLexerProfiles::custom(bracketPair: ['[', '']);
    }

    public function testRejectsEmptyOpeningNestingDelimiter(): void
    {
        $this->expectException(InvalidDefinitionException::class);

        FakeSqlLexerProfiles::custom(nestingPair: ['', ')']);
    }

    public function testRejectsEmptyClosingNestingDelimiter(): void
    {
        $this->expectException(InvalidDefinitionException::class);

        FakeSqlLexerProfiles::custom(nestingPair: ['(', '']);
    }

    public function testRejectsInvalidStatementDelimiterLength(): void
    {
        $this->expectException(InvalidDefinitionException::class);

        FakeSqlLexerProfiles::custom(statementDelimiter: ';;');
    }

    public function testRejectsInvalidListDelimiterLength(): void
    {
        $this->expectException(InvalidDefinitionException::class);

        FakeSqlLexerProfiles::custom(listDelimiter: '::');
    }

    public function testRejectsEmptyParameterPrefix(): void
    {
        $this->expectException(InvalidDefinitionException::class);

        FakeSqlLexerProfiles::custom(namedParameterSeparators: ['' => []]);
    }

    public function testRejectsEmptyParameterSeparator(): void
    {
        $this->expectException(InvalidDefinitionException::class);

        FakeSqlLexerProfiles::custom(namedParameterSeparators: [':' => ['']]);
    }

    public function testRejectsEmptyParameterPatternPrefix(): void
    {
        $this->expectException(InvalidDefinitionException::class);

        FakeSqlLexerProfiles::custom(namedParameterSuffixPatterns: ['' => '/^x/']);
    }

    public function testRejectsEmptyParameterPattern(): void
    {
        $this->expectException(InvalidDefinitionException::class);

        FakeSqlLexerProfiles::custom(namedParameterSuffixPatterns: [':' => '']);
    }

    public function testRejectsInvalidParameterPattern(): void
    {
        $this->expectException(InvalidDefinitionException::class);

        FakeSqlLexerProfiles::custom(namedParameterSuffixPatterns: [':' => '/[/']);
    }

    public function testRejectsInvalidPositionalParameterPattern(): void
    {
        $this->expectException(InvalidDefinitionException::class);

        FakeSqlLexerProfiles::custom(positionalParameterPatterns: ['/[/']);
    }

    public function testRestoresTheErrorHandlerAfterInvalidPattern(): void
    {
        $handled = false;
        set_error_handler(static function () use (&$handled): bool {
            $handled = true;

            return true;
        });
        $refused = null;
        try {
            try {
                FakeSqlLexerProfiles::custom(numericLiteralPattern: '/[/');
            } catch (InvalidDefinitionException $exception) {
                $refused = $exception;
            }
            trigger_error('error handler probe', E_USER_WARNING);
        } finally {
            restore_error_handler();
        }

        self::assertInstanceOf(InvalidDefinitionException::class, $refused);
        self::assertTrue($handled);
    }

    public function testRejectsInvalidLexicalPattern(): void
    {
        $this->expectException(InvalidDefinitionException::class);

        FakeSqlLexerProfiles::custom(numericLiteralPattern: '/[/');
    }
}
