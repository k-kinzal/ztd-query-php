<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Problem;

/**
 * A rule of SET, SHOW or EXPLAIN that grammatical SQL can break; each case holds the problem in the words of the server.
 *
 * Source: https://dev.mysql.com/doc/mysql-errors/8.4/en/server-error-reference.html.
 *
 * @visibility public
 * @example Reading the message of a rule
 *     \SqlSemantics\Platform\MySql\Statement\Utility\Problem\UtilityRule::UnknownExplainFormat->value // => 'Unknown EXPLAIN format name'
 */
enum UtilityRule: string
{
    case NamesExpression = 'SET NAMES takes a character set name, not an expression';
    case UnknownExplainFormat = 'Unknown EXPLAIN format name';
    case AnalyzeFormat = 'EXPLAIN ANALYZE does not support the TRADITIONAL format';
    case ExplainIntoFormat = 'EXPLAIN INTO requires FORMAT=JSON';
    case DebugOnly = 'The statement is available in debug builds of the server only';
}
