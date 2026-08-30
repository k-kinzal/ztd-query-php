<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\Postgres;

use ZtdQuery\Sql\Profile\SqlCommentProfile;
use ZtdQuery\Sql\Profile\SqlQuoteProfile;
use ZtdQuery\Sql\Profile\SqlParameterProfile;
use ZtdQuery\Sql\Profile\SqlSymbolProfile;
use ZtdQuery\Sql\SqlLexerProfile;

final class PgSqlLexerProfile
{
    public static function create(): SqlLexerProfile
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
                dollarQuoteDelimiterPattern: '/^\$(?:[_A-Za-z\x80-\xFF][_A-Za-z0-9\x80-\xFF]*)?\$/',
                backslashEscapedStringPrefixes: ['E', 'e'],
                backslashEscapedStrings: false,
            ),
            new SqlParameterProfile(
                positionalParameterPatterns: ['/^\$[0-9]+/', '/^\?/'],
                namedParameterSeparators: [':' => []],
                namedParameterSuffixPatterns: [],
                namedParameterForbiddenPredecessors: [':' => [':']],
            ),
            new SqlSymbolProfile(
                numericLiteralPattern: '/^(?:0[xX]_?[0-9A-Fa-f](?:_?[0-9A-Fa-f])*|0[oO]_?[0-7](?:_?[0-7])*|0[bB]_?[01](?:_?[01])*|(?:(?:[0-9](?:_?[0-9])*)(?:\.(?:[0-9](?:_?[0-9])*)?)?|\.(?:[0-9](?:_?[0-9])*))(?:[eE][+-]?[0-9](?:_?[0-9])*)?)/',
                identifierStartPattern: '/^[_A-Za-z\x80-\xFF]$/',
                identifierPartPattern: '/^[_A-Za-z0-9$\x80-\xFF]$/',
                bracketPair: ['[', ']'],
                nestingPair: ['(', ')'],
                statementDelimiter: ';',
                listDelimiter: ',',
            ),
        );
    }
}
