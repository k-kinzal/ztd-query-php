<?php

declare(strict_types=1);

namespace Tests\Fake;

use ZtdQuery\Sql\SqlLexerProfile;

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
     * @param array{string, string}|null $bracketPair
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
        ?string $dollarQuoteDelimiterPattern = null,
        string $numericLiteralPattern = '/^[0-9]+/',
        string $identifierStartPattern = '/^[_A-Za-z]$/',
        string $identifierPartPattern = '/^[_A-Za-z0-9]$/',
        ?array $bracketPair = null,
        bool $nestedBlockComments = false,
        bool $numberedDollarParameters = false,
        bool $questionMarkParameters = false,
        bool $numberedQuestionMarkParameters = false,
        bool $backslashEscapedStrings = false,
    ): SqlLexerProfile {
        return new SqlLexerProfile(
            lineCommentPrefixes: $lineCommentPrefixes,
            whitespaceDelimitedLineCommentPrefixes: $whitespaceDelimitedLineCommentPrefixes,
            blockCommentPairs: $blockCommentPairs,
            stringQuotePairs: $stringQuotePairs,
            identifierQuotePairs: $identifierQuotePairs,
            namedParameterSeparators: $namedParameterSeparators,
            namedParameterSuffixPatterns: $namedParameterSuffixPatterns,
            namedParameterForbiddenPredecessors: $namedParameterForbiddenPredecessors,
            backslashEscapedStringPrefixes: $backslashEscapedStringPrefixes,
            dollarQuoteDelimiterPattern: $dollarQuoteDelimiterPattern,
            numericLiteralPattern: $numericLiteralPattern,
            identifierStartPattern: $identifierStartPattern,
            identifierPartPattern: $identifierPartPattern,
            bracketPair: $bracketPair,
            nestedBlockComments: $nestedBlockComments,
            numberedDollarParameters: $numberedDollarParameters,
            questionMarkParameters: $questionMarkParameters,
            numberedQuestionMarkParameters: $numberedQuestionMarkParameters,
            backslashEscapedStrings: $backslashEscapedStrings,
        );
    }

    public static function standard(): SqlLexerProfile
    {
        return new SqlLexerProfile(
            lineCommentPrefixes: ['--'],
            whitespaceDelimitedLineCommentPrefixes: [],
            blockCommentPairs: ['/*' => '*/'],
            stringQuotePairs: ["'" => "'"],
            identifierQuotePairs: ['"' => '"'],
            namedParameterSeparators: [],
            namedParameterSuffixPatterns: [],
            namedParameterForbiddenPredecessors: [],
            backslashEscapedStringPrefixes: [],
            dollarQuoteDelimiterPattern: null,
            numericLiteralPattern: '/^(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)(?:[eE][+-]?[0-9]+)?/',
            identifierStartPattern: '/^[_A-Za-z\x80-\xFF]$/',
            identifierPartPattern: '/^[_A-Za-z0-9\x80-\xFF]$/',
            bracketPair: ['[', ']'],
            nestedBlockComments: true,
            numberedDollarParameters: false,
            questionMarkParameters: false,
            numberedQuestionMarkParameters: false,
            backslashEscapedStrings: false,
        );
    }

    public static function allCapabilities(): SqlLexerProfile
    {
        return new SqlLexerProfile(
            lineCommentPrefixes: ['--', '#'],
            whitespaceDelimitedLineCommentPrefixes: [],
            blockCommentPairs: ['/*' => '*/'],
            stringQuotePairs: ["'" => "'"],
            identifierQuotePairs: ['"' => '"', '`' => '`', '[' => ']'],
            namedParameterSeparators: [':' => ['::']],
            namedParameterSuffixPatterns: [':' => '/^\([^ \t\n\r\0\x0B)]*\)/'],
            namedParameterForbiddenPredecessors: [':' => [':']],
            backslashEscapedStringPrefixes: ['E', 'e'],
            dollarQuoteDelimiterPattern: '/^\$(?:[_A-Za-z][_A-Za-z0-9]*)?\$/',
            numericLiteralPattern: '/^(?:0[xX]_?[0-9A-Fa-f](?:_?[0-9A-Fa-f])*|(?:[0-9](?:_?[0-9])*)(?:\.(?:[0-9](?:_?[0-9])*)?)?(?:[eE][+-]?[0-9](?:_?[0-9])*)?)/',
            identifierStartPattern: '/^[_A-Za-z\x80-\xFF]$/',
            identifierPartPattern: '/^[_A-Za-z0-9$\x80-\xFF]$/',
            bracketPair: ['[', ']'],
            nestedBlockComments: true,
            numberedDollarParameters: true,
            questionMarkParameters: true,
            numberedQuestionMarkParameters: true,
            backslashEscapedStrings: true,
        );
    }
}
