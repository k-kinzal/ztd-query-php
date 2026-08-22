<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql;

use ZtdQuery\Sql\SqlLexerProfile;

final class MySqlLexerProfile
{
    public static function create(bool $ansiQuotes = false): SqlLexerProfile
    {
        return new SqlLexerProfile(
            lineCommentPrefixes: ['#'],
            whitespaceDelimitedLineCommentPrefixes: ['--'],
            blockCommentPairs: ['/*' => '*/'],
            stringQuotePairs: $ansiQuotes ? ["'" => "'"] : ["'" => "'", '"' => '"'],
            identifierQuotePairs: $ansiQuotes ? ['`' => '`', '"' => '"'] : ['`' => '`'],
            namedParameterSeparators: [':' => []],
            namedParameterSuffixPatterns: [],
            namedParameterForbiddenPredecessors: [':' => [':']],
            backslashEscapedStringPrefixes: [],
            dollarQuoteDelimiterPattern: null,
            numericLiteralPattern: '/^(?:0[xX][0-9A-Fa-f]+|(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)(?:[eE][+-]?[0-9]+)?)/',
            identifierStartPattern: '/^[_A-Za-z$\x80-\xFF]$/',
            identifierPartPattern: '/^[_A-Za-z0-9$\x80-\xFF]$/',
            bracketPair: ['[', ']'],
            nestedBlockComments: false,
            numberedDollarParameters: false,
            questionMarkParameters: true,
            numberedQuestionMarkParameters: false,
            backslashEscapedStrings: true,
        );
    }
}
