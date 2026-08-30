<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql;

use PhpMyAdmin\SqlParser\Context;
use ZtdQuery\Sql\SqlCommentProfile;
use ZtdQuery\Sql\SqlQuoteProfile;
use ZtdQuery\Sql\SqlParameterProfile;
use ZtdQuery\Sql\SqlSymbolProfile;
use ZtdQuery\Sql\SqlLexerProfile;

final class MySqlLexerProfile
{
    public static function create(?bool $ansiQuotes = null): SqlLexerProfile
    {
        $ansiQuotes ??= Context::hasMode(Context::SQL_MODE_ANSI_QUOTES);

        return new SqlLexerProfile(
            new SqlCommentProfile(
                lineCommentPrefixes: ['#'],
                whitespaceDelimitedLineCommentPrefixes: ['--'],
                blockCommentPairs: ['/*' => '*/'],
                nestedBlockComments: false,
            ),
            new SqlQuoteProfile(
                stringQuotePairs: $ansiQuotes ? ["'" => "'"] : ["'" => "'", '"' => '"'],
                identifierQuotePairs: $ansiQuotes ? ['`' => '`', '"' => '"'] : ['`' => '`'],
                dollarQuoteDelimiterPattern: null,
                backslashEscapedStringPrefixes: [],
                backslashEscapedStrings: true,
            ),
            new SqlParameterProfile(
                positionalParameterPatterns: ['/^\?/'],
                namedParameterSeparators: [':' => []],
                namedParameterSuffixPatterns: [],
                namedParameterForbiddenPredecessors: [':' => [':']],
            ),
            new SqlSymbolProfile(
                numericLiteralPattern: '/^(?:0[xX][0-9A-Fa-f]+|(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)(?:[eE][+-]?[0-9]+)?)/',
                identifierStartPattern: '/^[_A-Za-z$\x80-\xFF]$/',
                identifierPartPattern: '/^[_A-Za-z0-9$\x80-\xFF]$/',
                bracketPair: ['[', ']'],
                nestingPair: ['(', ')'],
                statementDelimiter: ';',
                listDelimiter: ',',
            ),
        );
    }
}
