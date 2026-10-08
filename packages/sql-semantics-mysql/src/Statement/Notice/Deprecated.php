<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Notice;

use SqlSemantics\Contract\GrammarRelease;

/**
 * A construct the server reads but warns about while it reads the statement: deprecated syntax, and syntax it converts.
 *
 * Each case holds the error number and the text of its warning. Release 5.6 warns only that
 * DELAYED is deprecated, and 5.7 that it is converted and that the query cache modifiers,
 * PROCEDURE ANALYSE, a direction in GROUP BY and a name after a leading dot are deprecated
 * (verified on live 5.6.51 and 5.7.44 servers).
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
    case DelayedInsert = "'INSERT DELAYED' is deprecated and will be removed in a future release. Please use INSERT instead";
    case DelayedReplace = "'REPLACE DELAYED' is deprecated and will be removed in a future release. Please use REPLACE instead";
    case DisplayWidth = 'Integer display width is deprecated and will be removed in a future release.';
    case Zerofill = 'The ZEROFILL attribute is deprecated and will be removed in a future release. Use the LPAD function to zero-pad numbers, or store the formatted numbers in a CHAR column.';
    case FloatingDigits = 'Specifying number of digits for floating point data types is deprecated and will be removed in a future release.';
    case UnsignedFraction = 'UNSIGNED for decimal and floating point data types is deprecated and support for it will be removed in a future release.';
    case YearWidth = "'YEAR(4)' is deprecated and will be removed in a future release. Please use YEAR instead";
    case NoCache = "'SQL_NO_CACHE' is deprecated and will be removed in a future release.";
    case Cache = "'SQL_CACHE' is deprecated and will be removed in a future release.";
    case DotColumn = "'.<table>.<column>' is deprecated and will be removed in a future release. Please use the table.column name without a dot prefix instead";
    case DotTable = "'.<table>' is deprecated and will be removed in a future release. Please use the table name without a dot prefix instead";
    case BinaryBitwise = "Bitwise operations on BINARY will change behavior in a future version, check the 'Bit functions' section in the manual.";
    case ProcedureAnalyse = "'PROCEDURE ANALYSE' is deprecated and will be removed in a future release.";
    case GroupByDirection = "'GROUP BY with ASC/DESC' is deprecated and will be removed in a future release. Please use GROUP BY ... ORDER BY ... ASC/DESC instead";
    case IntoInsideQuery = 'The INTO clause is deprecated inside query blocks of query expressions and will be removed in a future release. Please move the INTO clause to the end of statement instead.';
    case Utf8Alias = "'utf8' is currently an alias for the character set UTF8MB3, but will be an alias for UTF8MB4 in a future release. Please consider using UTF8MB4 in order to be unambiguous.";
    case Utf8mb3 = "'utf8mb3' is deprecated and will be removed in a future release. Please use utf8mb4 instead";
    case National = 'NATIONAL/NCHAR/NVARCHAR implies the character set UTF8MB3, which will be replaced by UTF8MB4 in a future release. Please consider using CHAR(x) CHARACTER SET UTF8MB4 in order to be unambiguous.';

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
            self::DisplayWidth, self::Zerofill, self::FloatingDigits, self::UnsignedFraction, self::NoCache, self::Cache, self::ProcedureAnalyse => 1681,
            self::Utf8Alias => 3719,
            self::National => 3720,
            self::IntoInsideQuery => 3962,
            self::DelayedInsert, self::DelayedReplace, self::GroupByDirection, self::BinaryBitwise, self::DotColumn, self::DotTable, self::PipesOr, self::AmpersandsAnd, self::BangNot, self::BinaryOperator, self::AssignmentInExpression, self::CalcFoundRows, self::FoundRows, self::ValuesFunction, self::YearWidth, self::Utf8mb3 => 1287,
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
        if ($release === GrammarRelease::MySql5651) {
            return $this === self::DelayedInsert || $this === self::DelayedReplace;
        }
        if ($release === GrammarRelease::MySql5744) {
            return in_array($this, [self::InsertDelayed, self::ReplaceDelayed, self::Cache, self::NoCache, self::ProcedureAnalyse, self::GroupByDirection, self::DotColumn, self::DotTable, self::BinaryBitwise], true);
        }

        return !in_array($this, [self::DelayedInsert, self::DelayedReplace, self::BinaryBitwise], true);
    }
}
