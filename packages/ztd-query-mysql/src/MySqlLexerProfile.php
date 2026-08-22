<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql;

use PhpMyAdmin\SqlParser\Context;
use ZtdQuery\Sql\SqlLexerProfile;

final class MySqlLexerProfile
{
    public static function create(?bool $ansiQuotes = null): SqlLexerProfile
    {
        $ansiQuotes ??= Context::hasMode(Context::SQL_MODE_ANSI_QUOTES);

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
            positionalParameterPatterns: ['/^\?/'],
            dollarQuoteDelimiterPattern: null,
            numericLiteralPattern: '/^(?:0[xX][0-9A-Fa-f]+|(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)(?:[eE][+-]?[0-9]+)?)/',
            identifierStartPattern: '/^[_A-Za-z$\x80-\xFF]$/',
            identifierPartPattern: '/^[_A-Za-z0-9$\x80-\xFF]$/',
            bracketPair: ['[', ']'],
            nestingPair: ['(', ')'],
            statementDelimiter: ';',
            listDelimiter: ',',
            nestedBlockComments: false,
            backslashEscapedStrings: true,
        );
    }
}
