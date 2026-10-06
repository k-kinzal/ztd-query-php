<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Utility;

use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Utility\Columns\ExplainReports;
use SqlSemantics\Platform\MySql\Rules\Utility\Columns\ReplicationReports;
use SqlSemantics\Platform\MySql\Rules\Utility\Columns\SchemaReports;
use SqlSemantics\Platform\MySql\Rules\Utility\Columns\ServerReports;
use SqlSemantics\Platform\MySql\Statement\Type\Character;
use SqlSemantics\Platform\MySql\Statement\Type\Decimal;
use SqlSemantics\Platform\MySql\Statement\Type\Floating;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\CharacterKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\FloatingKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\NumericModifier;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\TemporalKind;
use SqlSemantics\Platform\MySql\Statement\Type\Temporal;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Shape\OutputSlot;
use SqlSemantics\Statement\Type\Known;
use SqlSemantics\Statement\Type\Nullability;
use SqlSemantics\Statement\Type\NullOnly;
use SqlSemantics\Statement\Type\TypeFact;

/**
 * The columns of the rows SHOW and EXPLAIN return.
 *
 * Rule: MYSQL-SHOW-ROWS-001. The manual fixes the names and the order of
 * the columns of each SHOW and EXPLAIN form; the server fixes their types and
 * whether they can be NULL in the result metadata it sends, which differs
 * between releases. The tables (Columns/*Reports) hold, for each layout and
 * release, the names and those facts as the servers of the shipped releases
 * report them. A type is stated by its kind: the server computes the length
 * of several columns from the session (the character set of the
 * connection, the longest value), so lengths are not part of the facts.
 * Codes: v VARCHAR, c CHAR, t TEXT, m MEDIUMTEXT, l LONGTEXT, b BIGINT, B
 * BIGINT UNSIGNED, i INT, I INT UNSIGNED, d DECIMAL, f DOUBLE, F DOUBLE
 * UNSIGNED, D DATETIME, T TIMESTAMP, n NULL only (a column that is always
 * NULL). Precision: every column is a known type, or NULL only.
 * Terminates: a fixed table.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show.html and the pages of
 * each SHOW statement, https://dev.mysql.com/doc/refman/8.4/en/explain-output.html,
 * and the result metadata of the shipped server releases. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ShowRows
{
    /**
     * The type of each code, as a pair of a kind and its modifiers.
     */
    private const CHARACTERS = ['v' => CharacterKind::VarChar, 'c' => CharacterKind::Char, 't' => CharacterKind::Text, 'm' => CharacterKind::MediumText, 'l' => CharacterKind::LongText];

    /**
     * The integer codes, by kind and whether the type is unsigned.
     */
    private const INTEGERS = ['b' => [IntegralKind::BigInt, false], 'B' => [IntegralKind::BigInt, true], 'i' => [IntegralKind::Int, false], 'I' => [IntegralKind::Int, true]];

    /**
     * Answers the names and codes of the columns of a layout in a release.
     *
     * @return list<array{string, string}>
     */
    public function columns(Report $report, GrammarRelease $release): array
    {
        $tables = SchemaReports::ROWS + ServerReports::ROWS + ReplicationReports::ROWS + ExplainReports::ROWS;
        $row = null;
        foreach ($tables[$report->value] as $releases => $columns) {
            if (in_array($release->value, explode(' ', $releases), true)) {
                $row = $columns;
            }
        }
        Check::invariant($row !== null, 'The release ' . $release->value . ' has no ' . $report->value . ' layout.');
        $columns = [];
        foreach (explode('|', $row) as $column) {
            $colon = (int) strrpos($column, ':');
            $columns[] = [substr($column, 0, $colon), substr($column, $colon + 1)];
        }

        return $columns;
    }

    /**
     * Answers the output slots of columns given by names and codes.
     *
     * @param list<array{string, string}> $columns
     * @return list<OutputSlot>
     */
    public function slots(array $columns): array
    {
        $slots = [];
        foreach ($columns as [$name, $code]) {
            $nullable = str_ends_with($code, '?') || $code === 'n';
            $slots[] = new OutputSlot(new Name($name), $this->type(rtrim($code, '?')), $nullable ? Nullability::Nullable : Nullability::NotNull);
        }

        return $slots;
    }

    /**
     * Answers the type of a code.
     *
     * @throws \SqlSemantics\Diagnostic\InvariantViolation When the code is none of the codes of the tables
     */
    public function type(string $code): TypeFact
    {
        if (isset(self::CHARACTERS[$code])) {
            return new Known(new Character(self::CHARACTERS[$code]));
        }
        if (isset(self::INTEGERS[$code])) {
            [$kind, $unsigned] = self::INTEGERS[$code];

            return new Known(new Integral($kind, null, $unsigned ? [NumericModifier::Unsigned] : []));
        }

        return match ($code) {
            'd' => new Known(new Decimal()),
            'f' => new Known(new Floating(FloatingKind::Double)),
            'F' => new Known(new Floating(FloatingKind::Double, null, null, [NumericModifier::Unsigned])),
            'D' => new Known(new Temporal(TemporalKind::DateTime)),
            'T' => new Known(new Temporal(TemporalKind::Timestamp)),
            'n' => new NullOnly(),
            default => throw new \SqlSemantics\Diagnostic\InvariantViolation('Unknown column code ' . $code . '.'),
        };
    }
}
