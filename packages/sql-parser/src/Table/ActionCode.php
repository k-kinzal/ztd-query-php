<?php

declare(strict_types=1);

namespace SqlParser\Table;

/**
 * The integer encoding of one parse-table action.
 *
 * A shift or goto is the target state, zero or more. A reduce is the rule
 * negated and shifted by one, so reducing by rule zero, the accept, is minus
 * one. The smallest 32-bit integer marks a token the state rejects outright.
 *
 * @visibility root
 */
final class ActionCode
{
    /**
     * The code of an explicit error, as a non-associative operator leaves behind.
     */
    public const ERROR = -2147483648;

    /**
     * The code that accepts the input: a reduction by the augmented start rule.
     */
    public const ACCEPT = -1;

    /**
     * Encodes a shift or goto.
     *
     * @param int $state Target state
     *
     * @return int The action code
     */
    public static function shift(int $state): int
    {
        return $state;
    }

    /**
     * Encodes a reduction.
     *
     * @param int $rule Rule to reduce by
     *
     * @return int The action code
     */
    public static function reduce(int $rule): int
    {
        return -1 - $rule;
    }

    /**
     * Reports whether a code is a shift or goto.
     *
     * @param int $code Action code
     *
     * @return bool True for a shift
     */
    public static function isShift(int $code): bool
    {
        return $code >= 0;
    }

    /**
     * Reports whether a code is a reduction, the accept included.
     *
     * @param int $code Action code
     *
     * @return bool True for a reduction
     */
    public static function isReduce(int $code): bool
    {
        return $code < 0 && $code !== self::ERROR;
    }

    /**
     * Answers the rule a reduction code reduces by.
     *
     * @param int $code A reduction code
     *
     * @return int The rule index
     */
    public static function rule(int $code): int
    {
        return -1 - $code;
    }
}
