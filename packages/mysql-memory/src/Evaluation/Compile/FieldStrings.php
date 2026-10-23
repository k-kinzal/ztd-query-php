<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Compile;

use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Leaf\Constant;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Value\Temporal;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Statement\Scalar;

/**
 * Checks a constant string a comparison compares with a DATE, DATETIME or TIMESTAMP column, as the server converts the constant to a value of the column when it resolves the comparison.
 *
 * A string that holds a value of the column with more text after it warns that the value is
 * incorrect, naming the column and the first row, whatever the SQL mode, and compares as that
 * value. A string that holds
 * none, or a date the SQL mode refuses, warns so too, and MySQL 8.0 and later then refuse the
 * statement (ER_WRONG_VALUE) when the comparison is an operator, not IN or BETWEEN; MySQL 5.6
 * and 5.7 go on. A TIME column takes an empty string, notes a datetime (5.6 does not) and warns about anything
 * else that is no time (verified on live 5.6.51, 5.7.44, 8.0.44,
 * 8.4.7 and 9.1.0 servers).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/type-conversion.html.
 *
 * @visibility MySqlMemory\Evaluation
 */
final class FieldStrings
{
    /**
     * @param Compiler $compiler The compiler of the statement
     */
    public function __construct(public readonly Compiler $compiler)
    {
    }

    /**
     * Checks the constant of a comparison with a temporal column, and answers it as the comparison reads it: a string that holds a date or a datetime with more text after it as that value.
     *
     * @param bool $refuses Whether a constant that holds no value fails the statement, as a comparison operator does in MySQL 8.0 and later; IN and BETWEEN only warn
     * @throws \MySqlMemory\Error\SqlError When the constant holds no value of the column, in MySQL 8.0 and later
     */
    public function check(Scalar $columnNode, Evaluable $column, Scalar $constantNode, Evaluable $constant, bool $refuses = true): Evaluable
    {
        $domain = $column->domain();
        $name = (new FieldConstants($this->compiler))->column($columnNode);
        if ($name === null || !in_array($domain->kind, [Kind::Date, Kind::DateTime, Kind::Time], true) || $constant->domain()->kind !== Kind::String || !$this->compiler->constancy($constantNode)->constant()) {
            return $constant;
        }
        $value = $constant->evaluate(new Frame($this->compiler->connection->context));
        if ($value === null) {
            return $constant;
        }
        $text = (string) $value;
        if ($domain->kind === Kind::Time) {
            $this->time($text, $name);

            return $constant;
        }

        return $this->moment($domain, $text, $name, $constant, $refuses);
    }

    /**
     * Checks a string compared with a DATE, DATETIME or TIMESTAMP column, and answers it as the comparison reads it: a date or a datetime with more text after it as that value, with a warning naming the column.
     *
     * @param Domain $domain The domain of the column
     * @param string $text The string
     * @param string $name The name of the column
     * @param Evaluable $constant The constant that holds the string
     * @param bool $refuses Whether a string that holds no value fails the statement, in MySQL 8.0 and later
     * @throws \MySqlMemory\Error\SqlError When the string holds no value of the column, in MySQL 8.0 and later
     */
    public function moment(Domain $domain, string $text, string $name, Evaluable $constant, bool $refuses): Evaluable
    {
        $parts = Temporal::scanDateTime($text);
        $modes = $this->compiler->connection->context->modes;
        $valid = $parts !== null && $parts[3] <= 23 && $parts[4] <= 59 && $parts[5] <= 59 && Temporal::accepted($parts[0], $parts[1], $parts[2], $modes->has('NO_ZERO_DATE'), $modes->has('NO_ZERO_IN_DATE'));
        if ($valid && $parts[8] === '') {
            return $constant;
        }
        $this->warn($domain->kind === Kind::Date ? 'date' : 'datetime', $text, $name);
        if (!$valid && $refuses && !$this->compiler->settings->legacy()) {
            throw DataError::WrongValue->error(match (true) {
                $domain->kind === Kind::Date => 'DATE',
                $domain->field === Field::Timestamp => 'TIMESTAMP',
                default => 'DATETIME',
            }, $text);
        }

        return $valid ? new Constant($domain->withNullable(false), $domain->kind === Kind::Date ? Temporal::date($parts[0], $parts[1], $parts[2]) : Temporal::dateTime($parts[0], $parts[1], $parts[2], $parts[3], $parts[4], $parts[5], Temporal::micro($parts[6], true), $domain->decimals)) : $constant;
    }

    /**
     * Checks a string compared with a TIME column: an empty one or a time is taken, a datetime raises a note, except in MySQL 5.6, and anything else a warning, naming the column.
     */
    public function time(string $text, string $column): void
    {
        $time = Temporal::parseTime($text);
        if (trim($text) === '' || ($time !== null && $time[2] <= 59 && $time[3] <= 59)) {
            return;
        }
        $moment = Temporal::scanDateTime($text);
        if ($moment !== null && $moment[7] && $moment[8] === '') {
            if ($this->compiler->settings->release() === \SqlSemantics\Contract\GrammarRelease::MySql5651) {
                return;
            }
            $this->compiler->connection->context->diagnostics->note(DataError::TruncatedWrongValue, sprintf("Incorrect time value: '%s' for column '%s' at row 1", $text, $column));

            return;
        }
        $this->warn('time', $text, $column);
    }

    /**
     * Records the warning of a string that is no correct value of a column, naming the column and the first row.
     */
    public function warn(string $type, string $text, string $column): void
    {
        $this->compiler->connection->context->diagnostics->warning(1292, sprintf("Incorrect %s value: '%s' for column '%s' at row 1", $type, $text, $column));
    }
}
