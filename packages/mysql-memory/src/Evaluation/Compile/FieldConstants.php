<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Compile;

use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Leaf\Constant;
use MySqlMemory\Typing\Domain;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberForm;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\SignedLiteral;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Statement\Scalar;

/**
 * Reads a number a comparison compares with a DATE or DATETIME column as a value of the column, as the server stores the constant into the column when it resolves the statement.
 *
 * The digits of the number spell a date or a datetime: YYMMDD, YYYYMMDD, YYMMDDhhmmss or
 * YYYYMMDDhhmmss, a two-digit year below 70 in the 2000s and from 70 in the 1900s; the day is
 * checked against 31 only, as a comparison takes invalid dates. A DATETIME column then compares
 * as a datetime. A number that spells no date warns once, naming the column and the first row,
 * and compares as a number; a fraction warns too (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/date-and-time-literals.html,
 * https://dev.mysql.com/doc/refman/8.4/en/type-conversion.html.
 *
 * @visibility MySqlMemory\Evaluation
 */
final class FieldConstants
{
    /**
     * @param Compiler $compiler The compiler of the statement
     */
    public function __construct(public readonly Compiler $compiler)
    {
    }

    /**
     * Answers the constant of a comparison with a temporal column as a value of the column, or as it is.
     */
    public function stored(Scalar $columnNode, Evaluable $column, Scalar $constantNode, Evaluable $constant): Evaluable
    {
        $domain = $column->domain();
        $field = $this->column($columnNode);
        $text = $this->number($constantNode);
        if ($field === null || $text === null || ($domain->kind !== Kind::Date && $domain->kind !== Kind::DateTime)) {
            return $constant;
        }
        [$moment, $fraction] = $this->moment($text);
        if ($moment === null || $fraction) {
            $this->compiler->connection->context->diagnostics->warning(1292, sprintf("Incorrect %s value: '%s' for column '%s' at row 1", $domain->kind === Kind::Date ? 'date' : 'datetime', $text, $field));
        }
        if ($moment === null || $domain->kind !== Kind::DateTime) {
            return $constant;
        }

        return new Constant(new Domain(Kind::DateTime, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field::DateTime, 19, 0, false, null, false), $moment);
    }

    /**
     * Answers the name of the column a node reads, or null for another node.
     */
    public function column(Scalar $node): ?string
    {
        while ($node instanceof Grouped) {
            $node = $node->operand;
        }

        return $node instanceof ColumnUse ? $node->name->value : null;
    }

    /**
     * Answers the text of a numeric literal other than a float, signed, or null for another node.
     */
    public function number(Scalar $node): ?string
    {
        while ($node instanceof Grouped) {
            $node = $node->operand;
        }
        $negative = false;
        if ($node instanceof SignedLiteral) {
            $negative = $node->negative;
            $node = $node->number;
        }
        if ($node instanceof \SqlSemantics\Platform\MySql\Statement\Expression\Operator\Unary && $node->operator === \SqlSemantics\Platform\MySql\Statement\Expression\Operator\UnaryOperator::Minus) {
            $negative = true;
            $node = $node->operand;
        }
        if (!$node instanceof NumberLiteral || $node->form === NumberForm::Float) {
            return null;
        }

        return ($negative ? '-' : '') . $node->text;
    }

    /**
     * Answers the datetime the digits of a number spell, or null when they spell none, and whether it has a fraction.
     *
     * @return array{string|null, bool}
     */
    public function moment(string $text): array
    {
        $fraction = false;
        if (str_contains($text, '.')) {
            [$text, $decimals] = explode('.', $text, 2);
            $fraction = trim($decimals, '0') !== '';
        }
        if ($text === '' || !ctype_digit($text) || strlen($text) > 14) {
            return [null, $fraction];
        }
        $digits = $this->digits((int) $text);
        if ($digits === 0) {
            return ['0000-00-00 00:00:00', $fraction];
        }

        return [$digits === null ? null : $this->spelled($digits), $fraction];
    }

    /**
     * Answers the digits YYYYMMDD or YYYYMMDDhhmmss a number spells, with a two-digit year below
     * 70 in the 2000s and from 70 in the 1900s; 0 for zero, or null for a number of no such form.
     */
    public function digits(int $number): ?int
    {
        return match (true) {
            $number === 0 => 0,
            $number < 101 => null,
            $number <= 691231 => 20000000 + $number,
            $number < 700101 => null,
            $number <= 991231 => 19000000 + $number,
            $number < 10000101 => null,
            $number <= 99991231 => $number,
            $number < 101000000 => null,
            $number <= 691231235959 => 20000000000000 + $number,
            $number < 700101000000 => null,
            $number <= 991231235959 => 19000000000000 + $number,
            $number < 10000101000000 => null,
            default => $number,
        };
    }

    /**
     * Answers the datetime the digits YYYYMMDD or YYYYMMDDhhmmss spell, or null when a part is out
     * of range; the day is checked against 31 only.
     */
    public function spelled(int $digits): ?string
    {
        $spelled = str_pad((string) $digits, $digits > 99991231 ? 14 : 8, '0', STR_PAD_LEFT);
        $year = (int) substr($spelled, 0, 4);
        $month = (int) substr($spelled, 4, 2);
        $day = (int) substr($spelled, 6, 2);
        $hour = (int) substr($spelled, 8, 2);
        $minute = (int) substr($spelled, 10, 2);
        $second = (int) substr($spelled, 12, 2);
        if ($month < 1 || $month > 12 || $day < 1 || $day > 31 || $hour > 23 || $minute > 59 || $second > 59) {
            return null;
        }

        return sprintf('%04d-%02d-%02d %02d:%02d:%02d', $year, $month, $day, $hour, $minute, $second);
    }
}
