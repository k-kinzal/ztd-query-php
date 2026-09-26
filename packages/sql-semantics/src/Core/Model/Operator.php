<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Model;

/**
 * A scalar operator the binder models, independent of how a dialect spells it.
 *
 * @example Reading semantic facts
 *     \SqlSemantics\Core\Model\Operator::fromSpelling('!=') === \SqlSemantics\Core\Model\Operator::NotEqual // => true
 *     \SqlSemantics\Core\Model\Operator::IsNull->isBinary() // => false
 *
 * @visibility public
 */
enum Operator: string
{
    case Plus = '+';
    case Minus = '-';
    case Multiply = '*';
    case Equal = '=';
    case NotEqual = '<>';
    case Less = '<';
    case Greater = '>';
    case LessOrEqual = '<=';
    case GreaterOrEqual = '>=';
    case NullSafeEqual = '<=>';
    case Is = 'IS';
    case IsNull = 'IS NULL';
    case IsNotNull = 'IS NOT NULL';
    case And = 'AND';
    case Or = 'OR';
    case Not = 'NOT';

    /**
     * Resolves an operator from its SQL spelling, accepting synonyms and any letter case.
     */
    public static function fromSpelling(string $spelling): ?self
    {
        $text = strtoupper(trim((string) preg_replace('/\s+/', ' ', $spelling)));

        return $text === '!=' ? self::NotEqual : self::tryFrom($text);
    }

    /**
     * Reports whether the operator combines two operands.
     */
    public function isBinary(): bool
    {
        return !in_array($this, [self::Not, self::IsNull, self::IsNotNull], true);
    }

    /**
     * Reports whether the operator computes a number.
     */
    public function isArithmetic(): bool
    {
        return in_array($this, [self::Plus, self::Minus, self::Multiply], true);
    }

    /**
     * Reports whether the operator compares two values, including NULL-safe comparison.
     */
    public function isComparison(): bool
    {
        return in_array($this, [self::Equal, self::NotEqual, self::Less, self::Greater, self::LessOrEqual, self::GreaterOrEqual, self::NullSafeEqual, self::Is], true);
    }

    /**
     * Reports whether the operator combines or negates truth values.
     */
    public function isLogical(): bool
    {
        return in_array($this, [self::And, self::Or, self::Not], true);
    }

    /**
     * Reports whether the operator tests for NULL and therefore never yields NULL.
     */
    public function isNullTest(): bool
    {
        return in_array($this, [self::IsNull, self::IsNotNull], true);
    }
}
