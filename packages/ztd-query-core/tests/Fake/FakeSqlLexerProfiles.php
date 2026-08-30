<?php

declare(strict_types=1);

namespace Tests\Fake;

use ZtdQuery\Exception\InvalidDefinitionException;
use ZtdQuery\Sql\Profile\SqlCommentProfile;
use ZtdQuery\Sql\Profile\SqlParameterProfile;
use ZtdQuery\Sql\Profile\SqlQuoteProfile;
use ZtdQuery\Sql\Profile\SqlSymbolProfile;
use ZtdQuery\Sql\SqlLexerProfile;

/**
 * Lexer profiles for tests, built around whatever one test is about.
 *
 * A profile takes twenty arguments before it describes anything, and a test
 * about one of them has no interest in the other nineteen.
 */
final class FakeSqlLexerProfiles
{
    /**
     * @param list<string> $lineCommentPrefixes
     * @param list<string> $whitespaceDelimitedLineCommentPrefixes
     * @param array<string, string> $blockCommentPairs
     * @param array<string, string> $stringQuotePairs
     * @param array<string, string> $identifierQuotePairs
     * @param array<string, list<string>> $namedParameterSeparators
     * @param array<string, string> $namedParameterSuffixPatterns
     * @param array<string, list<string>> $namedParameterForbiddenPredecessors
     * @param list<string> $backslashEscapedStringPrefixes
     * @param list<string> $positionalParameterPatterns
     * @param array{string, string}|null $bracketPair
     * @param array{string, string} $nestingPair
     *
     * @throws InvalidDefinitionException When the lexical data is not something a scanner could use
     */
    public static function custom(
        array $lineCommentPrefixes = ['--'],
        array $whitespaceDelimitedLineCommentPrefixes = [],
        array $blockCommentPairs = ['/*' => '*/'],
        array $stringQuotePairs = ["'" => "'"],
        array $identifierQuotePairs = ['"' => '"'],
        array $namedParameterSeparators = [],
        array $namedParameterSuffixPatterns = [],
        array $namedParameterForbiddenPredecessors = [],
        array $backslashEscapedStringPrefixes = [],
        array $positionalParameterPatterns = [],
        ?string $dollarQuoteDelimiterPattern = null,
        string $numericLiteralPattern = '/^[0-9]+/',
        string $identifierStartPattern = '/^[_A-Za-z]$/',
        string $identifierPartPattern = '/^[_A-Za-z0-9]$/',
        ?array $bracketPair = null,
        array $nestingPair = ['(', ')'],
        string $statementDelimiter = ';',
        string $listDelimiter = ',',
        bool $nestedBlockComments = false,
        bool $backslashEscapedStrings = false,
    ): SqlLexerProfile {
        return new SqlLexerProfile(
            new SqlCommentProfile(
                lineCommentPrefixes: $lineCommentPrefixes,
                whitespaceDelimitedLineCommentPrefixes: $whitespaceDelimitedLineCommentPrefixes,
                blockCommentPairs: $blockCommentPairs,
                nestedBlockComments: $nestedBlockComments,
            ),
            new SqlQuoteProfile(
                stringQuotePairs: $stringQuotePairs,
                identifierQuotePairs: $identifierQuotePairs,
                dollarQuoteDelimiterPattern: $dollarQuoteDelimiterPattern,
                backslashEscapedStringPrefixes: $backslashEscapedStringPrefixes,
                backslashEscapedStrings: $backslashEscapedStrings,
            ),
            new SqlParameterProfile(
                positionalParameterPatterns: $positionalParameterPatterns,
                namedParameterSeparators: $namedParameterSeparators,
                namedParameterSuffixPatterns: $namedParameterSuffixPatterns,
                namedParameterForbiddenPredecessors: $namedParameterForbiddenPredecessors,
            ),
            new SqlSymbolProfile(
                numericLiteralPattern: $numericLiteralPattern,
                identifierStartPattern: $identifierStartPattern,
                identifierPartPattern: $identifierPartPattern,
                bracketPair: $bracketPair,
                nestingPair: $nestingPair,
                statementDelimiter: $statementDelimiter,
                listDelimiter: $listDelimiter,
            ),
        );
    }

    /**
     * Standard.
     *
     * @return SqlLexerProfile
     */
    public static function standard(): SqlLexerProfile
    {
        return new SqlLexerProfile(
            new SqlCommentProfile(
                lineCommentPrefixes: ['--'],
                whitespaceDelimitedLineCommentPrefixes: [],
                blockCommentPairs: ['/*' => '*/'],
                nestedBlockComments: true,
            ),
            new SqlQuoteProfile(
                stringQuotePairs: ["'" => "'"],
                identifierQuotePairs: ['"' => '"'],
                dollarQuoteDelimiterPattern: null,
                backslashEscapedStringPrefixes: [],
                backslashEscapedStrings: false,
            ),
            new SqlParameterProfile(
                positionalParameterPatterns: [],
                namedParameterSeparators: [],
                namedParameterSuffixPatterns: [],
                namedParameterForbiddenPredecessors: [],
            ),
            new SqlSymbolProfile(
                numericLiteralPattern: '/^(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)(?:[eE][+-]?[0-9]+)?/',
                identifierStartPattern: '/^[_A-Za-z\x80-\xFF]$/',
                identifierPartPattern: '/^[_A-Za-z0-9\x80-\xFF]$/',
                bracketPair: ['[', ']'],
                nestingPair: ['(', ')'],
                statementDelimiter: ';',
                listDelimiter: ',',
            ),
        );
    }

    /**
     * All capabilities.
     *
     * @return SqlLexerProfile
     */
    public static function allCapabilities(): SqlLexerProfile
    {
        return new SqlLexerProfile(
            new SqlCommentProfile(
                lineCommentPrefixes: ['--', '#'],
                whitespaceDelimitedLineCommentPrefixes: [],
                blockCommentPairs: ['/*' => '*/'],
                nestedBlockComments: true,
            ),
            new SqlQuoteProfile(
                stringQuotePairs: ["'" => "'"],
                identifierQuotePairs: ['"' => '"', '`' => '`', '[' => ']'],
                dollarQuoteDelimiterPattern: '/^\$(?:[_A-Za-z][_A-Za-z0-9]*)?\$/',
                backslashEscapedStringPrefixes: ['E', 'e'],
                backslashEscapedStrings: true,
            ),
            new SqlParameterProfile(
                positionalParameterPatterns: ['/^\$[0-9]+/', '/^\?[0-9]*/'],
                namedParameterSeparators: [':' => ['::']],
                namedParameterSuffixPatterns: [':' => '/^\([^ \t\n\r\0\x0B)]*\)/'],
                namedParameterForbiddenPredecessors: [':' => [':']],
            ),
            new SqlSymbolProfile(
                numericLiteralPattern: '/^(?:0[xX]_?[0-9A-Fa-f](?:_?[0-9A-Fa-f])*|(?:[0-9](?:_?[0-9])*)(?:\.(?:[0-9](?:_?[0-9])*)?)?(?:[eE][+-]?[0-9](?:_?[0-9])*)?)/',
                identifierStartPattern: '/^[_A-Za-z\x80-\xFF]$/',
                identifierPartPattern: '/^[_A-Za-z0-9$\x80-\xFF]$/',
                bracketPair: ['[', ']'],
                nestingPair: ['(', ')'],
                statementDelimiter: ';',
                listDelimiter: ',',
            ),
        );
    }

    /**
     * How a dialect writes a comment, built around whatever one test is about.
     *
     * @param list<string> $lineCommentPrefixes
     * @param list<string> $whitespaceDelimitedLineCommentPrefixes
     * @param array<string, string> $blockCommentPairs
     *
     * @throws InvalidDefinitionException When the lexical data is not something a scanner could use
     */
    public static function comments(
        array $lineCommentPrefixes = ['--'],
        array $whitespaceDelimitedLineCommentPrefixes = [],
        array $blockCommentPairs = ['/*' => '*/'],
        bool $nestedBlockComments = false,
    ): SqlCommentProfile {
        return new SqlCommentProfile(
            lineCommentPrefixes: $lineCommentPrefixes,
            whitespaceDelimitedLineCommentPrefixes: $whitespaceDelimitedLineCommentPrefixes,
            blockCommentPairs: $blockCommentPairs,
            nestedBlockComments: $nestedBlockComments,
        );
    }

    /**
     * How a dialect quotes, built around whatever one test is about.
     *
     * @param array<string, string> $stringQuotePairs
     * @param array<string, string> $identifierQuotePairs
     * @param list<string> $backslashEscapedStringPrefixes
     *
     * @throws InvalidDefinitionException When the lexical data is not something a scanner could use
     */
    public static function quotes(
        array $stringQuotePairs = ["'" => "'"],
        array $identifierQuotePairs = ['"' => '"'],
        ?string $dollarQuoteDelimiterPattern = null,
        array $backslashEscapedStringPrefixes = [],
        bool $backslashEscapedStrings = false,
    ): SqlQuoteProfile {
        return new SqlQuoteProfile(
            stringQuotePairs: $stringQuotePairs,
            identifierQuotePairs: $identifierQuotePairs,
            dollarQuoteDelimiterPattern: $dollarQuoteDelimiterPattern,
            backslashEscapedStringPrefixes: $backslashEscapedStringPrefixes,
            backslashEscapedStrings: $backslashEscapedStrings,
        );
    }

    /**
     * How a dialect writes a placeholder, built around whatever one test is about.
     *
     * @param list<string> $positionalParameterPatterns
     * @param array<string, list<string>> $namedParameterSeparators
     * @param array<string, string> $namedParameterSuffixPatterns
     * @param array<string, list<string>> $namedParameterForbiddenPredecessors
     *
     * @throws InvalidDefinitionException When the lexical data is not something a scanner could use
     */
    public static function parameters(
        array $positionalParameterPatterns = [],
        array $namedParameterSeparators = [],
        array $namedParameterSuffixPatterns = [],
        array $namedParameterForbiddenPredecessors = [],
    ): SqlParameterProfile {
        return new SqlParameterProfile(
            positionalParameterPatterns: $positionalParameterPatterns,
            namedParameterSeparators: $namedParameterSeparators,
            namedParameterSuffixPatterns: $namedParameterSuffixPatterns,
            namedParameterForbiddenPredecessors: $namedParameterForbiddenPredecessors,
        );
    }

    /**
     * How a dialect spells what is written plainly, built around whatever one test is about.
     *
     * @param array{string, string}|null $bracketPair
     * @param array{string, string} $nestingPair
     *
     * @throws InvalidDefinitionException When the lexical data is not something a scanner could use
     */
    public static function symbols(
        string $numericLiteralPattern = '/^[0-9]+/',
        string $identifierStartPattern = '/^[_A-Za-z]$/',
        string $identifierPartPattern = '/^[_A-Za-z0-9]$/',
        ?array $bracketPair = null,
        array $nestingPair = ['(', ')'],
        string $statementDelimiter = ';',
        string $listDelimiter = ',',
    ): SqlSymbolProfile {
        return new SqlSymbolProfile(
            numericLiteralPattern: $numericLiteralPattern,
            identifierStartPattern: $identifierStartPattern,
            identifierPartPattern: $identifierPartPattern,
            bracketPair: $bracketPair,
            nestingPair: $nestingPair,
            statementDelimiter: $statementDelimiter,
            listDelimiter: $listDelimiter,
        );
    }
}
