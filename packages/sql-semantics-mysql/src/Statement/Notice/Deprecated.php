<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Notice;

use SqlSemantics\Contract\GrammarRelease;

/**
 * A construct the server reads but warns about while it reads the statement: deprecated syntax, and syntax it converts.
 *
 * Each case holds the error number and the text of its warning. Releases 5.6 and 5.7 warn only
 * about DELAYED.
 * Source: https://dev.mysql.com/doc/mysql-errors/8.4/en/server-error-reference.html#error_er_warn_deprecated_syntax.
 *
 * @visibility public
 * @example Reading the warning of a construct
 *     [\SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::BangNot->code(), \SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::BangNot->value] // => [1287, "'!' is deprecated and will be removed in a future release. Please use NOT instead"]
 */
enum Deprecated: string
{
    case PipesOr = "'|| as a synonym for OR' is deprecated and will be removed in a future release. Please use OR instead";
    case AmpersandsAnd = "'&&' is deprecated and will be removed in a future release. Please use AND instead";
    case BangNot = "'!' is deprecated and will be removed in a future release. Please use NOT instead";
    case BinaryOperator = "'BINARY expr' is deprecated and will be removed in a future release. Please use CAST instead";
    case AssignmentInExpression = "Setting user variables within expressions is deprecated and will be removed in a future release. Consider alternatives: 'SET variable=expression, ...', or 'SELECT expression(s) INTO variables(s)'.";
    case CalcFoundRows = 'SQL_CALC_FOUND_ROWS is deprecated and will be removed in a future release. Consider using two separate queries instead.';
    case FoundRows = 'FOUND_ROWS() is deprecated and will be removed in a future release. Consider using COUNT(*) instead.';
    case ValuesFunction = "'VALUES function' is deprecated and will be removed in a future release. Please use an alias (INSERT INTO ... VALUES (...) AS alias) and replace VALUES(col) in the ON DUPLICATE KEY UPDATE clause with alias.col instead";
    case InsertDelayed = 'INSERT DELAYED is no longer supported. The statement was converted to INSERT.';
    case ReplaceDelayed = 'REPLACE DELAYED is no longer supported. The statement was converted to REPLACE.';
    case DisplayWidth = 'Integer display width is deprecated and will be removed in a future release.';
    case Zerofill = 'The ZEROFILL attribute is deprecated and will be removed in a future release. Use the LPAD function to zero-pad numbers, or store the formatted numbers in a CHAR column.';
    case FloatingDigits = 'Specifying number of digits for floating point data types is deprecated and will be removed in a future release.';
    case UnsignedFraction = 'UNSIGNED for decimal and floating point data types is deprecated and support for it will be removed in a future release.';
    case YearWidth = "'YEAR(4)' is deprecated and will be removed in a future release. Please use YEAR instead";
    case NoCache = "'SQL_NO_CACHE' is deprecated and will be removed in a future release.";
    case Utf8Alias = "'utf8' is currently an alias for the character set UTF8MB3, but will be an alias for UTF8MB4 in a future release. Please consider using UTF8MB4 in order to be unambiguous.";

    /**
     * Answers the error number the warning is raised with.
     *
     * @example A conversion of legacy syntax
     *     \SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::InsertDelayed->code() // => 3005
     */
    public function code(): int
    {
        return match ($this) {
            self::InsertDelayed, self::ReplaceDelayed => 3005,
            self::DisplayWidth, self::Zerofill, self::FloatingDigits, self::UnsignedFraction, self::NoCache => 1681,
            self::Utf8Alias => 3719,
            default => 1287,
        };
    }

    /**
     * Tells whether a release warns about the construct.
     *
     * @example Releases before 8.0
     *     \SqlSemantics\Platform\MySql\Statement\Notice\Deprecated::BangNot->warnedIn(\SqlSemantics\Contract\GrammarRelease::MySql5744) // => false
     */
    public function warnedIn(GrammarRelease $release): bool
    {
        if ($release === GrammarRelease::MySql5651 || $release === GrammarRelease::MySql5744) {
            return $this === self::InsertDelayed || $this === self::ReplaceDelayed;
        }

        return true;
    }
}
