<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Expression;

use SqlSemantics\Platform\MySql\Statement\Type\Decimal;
use SqlSemantics\Platform\MySql\Statement\Type\Elementary;
use SqlSemantics\Platform\MySql\Statement\Type\Floating;
use SqlSemantics\Platform\MySql\Statement\Type\Integral;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\ElementaryKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\FloatingKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\IntegralKind;
use SqlSemantics\Platform\MySql\Statement\Type\Kind\NumericModifier;
use SqlSemantics\Statement\Type\TypeDescriptor;

/**
 * Merges two numeric types.
 *
 * Rule: MYSQL-NUMERIC-MERGE-001, a part of MYSQL-TYPE-AGGREGATION-001. BOOL
 * is TINYINT, SERIAL and BIT are BIGINT UNSIGNED. Two integers merge to the
 * wider kind, unsigned when both are; an unsigned and a signed integer need
 * the next wider signed kind, and BIGINT with BIGINT UNSIGNED needs
 * DECIMAL. An integer with DECIMAL gives DECIMAL. Two FLOATs stay FLOAT; any
 * other mixture with FLOAT, DOUBLE or REAL gives DOUBLE. Terminates: no recursion.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/union.html,
 * https://dev.mysql.com/doc/refman/8.4/en/numeric-type-syntax.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class NumericMerge
{
    /**
     * The integer kinds in order of width.
     */
    private const WIDTHS = ['TINYINT' => 1, 'SMALLINT' => 2, 'MEDIUMINT' => 3, 'INT' => 4, 'BIGINT' => 5];

    /**
     * The integer kinds by width.
     */
    private const KINDS = [1 => IntegralKind::TinyInt, 2 => IntegralKind::SmallInt, 3 => IntegralKind::MediumInt, 4 => IntegralKind::Int, 5 => IntegralKind::BigInt];

    /**
     * Answers the common type of two numeric types.
     */
    public function merge(TypeDescriptor $left, TypeDescriptor $right): TypeDescriptor
    {
        $left = $this->integral($left);
        $right = $this->integral($right);
        if ($left instanceof Integral && $right instanceof Integral) {
            return $this->integers($left, $right);
        }
        if ($left instanceof Floating || $right instanceof Floating) {
            $float = $left instanceof Floating && $right instanceof Floating && $left->kind === FloatingKind::Float && $right->kind === FloatingKind::Float;

            return new Floating($float ? FloatingKind::Float : FloatingKind::Double);
        }

        return new Decimal();
    }

    /**
     * Answers the integer type BOOL, SERIAL and BIT stand for, and every other type unchanged.
     */
    public function integral(TypeDescriptor $type): TypeDescriptor
    {
        if (!$type instanceof Elementary) {
            return $type;
        }

        return match ($type->kind) {
            ElementaryKind::Boolean => new Integral(IntegralKind::TinyInt),
            ElementaryKind::Serial, ElementaryKind::Bit => new Integral(IntegralKind::BigInt, null, [NumericModifier::Unsigned]),
            ElementaryKind::Json, ElementaryKind::Vector => new Floating(FloatingKind::Double),
        };
    }

    /**
     * Answers the common type of two integer types.
     */
    public function integers(Integral $left, Integral $right): TypeDescriptor
    {
        $width = max(self::WIDTHS[$left->kind->value], self::WIDTHS[$right->kind->value]);
        if ($left->unsigned() === $right->unsigned()) {
            return new Integral(self::KINDS[$width], null, $left->unsigned() ? [NumericModifier::Unsigned] : []);
        }
        $unsigned = $left->unsigned() ? $left : $right;
        $needed = max($width, self::WIDTHS[$unsigned->kind->value] + 1);

        return $needed > 5 ? new Decimal() : new Integral(self::KINDS[$needed]);
    }
}
