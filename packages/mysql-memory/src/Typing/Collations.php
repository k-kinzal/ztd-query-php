<?php

declare(strict_types=1);

namespace MySqlMemory\Typing;

use MySqlMemory\Error\ErrorCode;
use MySqlMemory\Error\SqlError;

/**
 * Decides the collation an operation over several strings compares or produces them in.
 *
 * The operand of the lowest coercibility decides. Between two of one level, a binary string
 * wins over a character string, a Unicode character set over a narrower one when the narrower
 * operand is coercible, and the `_bin` collation of one character set over another collation of
 * it; any other mix at the same level is an error (ER_CANT_AGGREGATE_2COLLATIONS).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/charset-collation-coercibility.html.
 *
 * @visibility MySqlMemory
 */
final class Collations
{
    /**
     * Answers the collation and coercibility of an operation over operand domains.
     *
     * @param list<Domain> $domains The operands; numbers take part with their own level
     *
     * @return array{Collation, Coercibility}
     *
     * @throws SqlError When two operands of one level have collations that do not mix
     */
    public static function aggregate(array $domains, string $operation, Collation $connection): array
    {
        $collation = null;
        $level = Coercibility::Ignorable;
        foreach ($domains as $domain) {
            $candidate = $domain->kind === Kind::String || $domain->kind === Kind::Json ? $domain->collation : $connection;
            $candidateLevel = $domain->kind === Kind::Null ? Coercibility::Ignorable : ($domain->kind === Kind::String || $domain->kind === Kind::Json ? $domain->coercibility : Coercibility::Numeric);
            if ($collation === null || $candidateLevel->value < $level->value) {
                [$collation, $level] = [$candidate, $candidateLevel];
                continue;
            }
            if ($candidateLevel !== $level || $candidate === $collation) {
                continue;
            }
            $collation = self::tie($collation, $candidate, $level, $operation);
        }

        return [$collation ?? $connection, $level];
    }

    /**
     * Decides between two collations of operands of one level.
     *
     * @throws SqlError When the collations do not mix
     */
    public static function tie(Collation $left, Collation $right, Coercibility $level, string $operation): Collation
    {
        if ($left === Collation::Binary || $right === Collation::Binary) {
            return Collation::Binary;
        }
        if ($left->charset() === $right->charset()) {
            if ($left->binary() !== $right->binary()) {
                return $left->binary() ? $left : $right;
            }
        }
        if ($level === Coercibility::Coercible || $level === Coercibility::Numeric || $level === Coercibility::Ignorable) {
            return $left->charset() === Charset::Utf8mb4 ? $left : ($right->charset() === Charset::Utf8mb4 ? $right : $left);
        }
        if ($level === Coercibility::None) {
            return $left;
        }

        throw ErrorCode::CantAggregateTwoCollations->error($left->value, strtoupper($level->name === 'Implicit' ? 'IMPLICIT' : $level->name), $right->value, strtoupper($level->name === 'Implicit' ? 'IMPLICIT' : $level->name), $operation);
    }
}
