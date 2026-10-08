<?php

declare(strict_types=1);

namespace MySqlMemory\Typing;

use MySqlMemory\Error\DataError;
use MySqlMemory\Error\SqlError;
use SqlSemantics\Platform\MySql\Rules\Typing\Collations as Rules;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\IllegalCollationMix;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;

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
     * Answers the collation and coercibility of an operation by the rules of SQL Semantics.
     *
     * @param list<Domain> $domains The operands
     * @param string $operation The operation as the server names it
     * @param Collation $connection The collation a value that is not a string is written in
     * @param bool $comparison Whether the operation compares
     * @return array{Collation, Coercibility}
     *
     * @throws SqlError When the collations conflict
     */
    public static function aggregate(array $domains, string $operation, Collation $connection, bool $comparison = false): array
    {
        $rules = new Rules($connection);
        $resolved = array_map(static fn (Domain $domain) => $domain->resolved(), $domains);
        $settled = $rules->settle($resolved, $comparison);
        if ($settled !== null) {
            return $settled;
        }
        $mix = new IllegalCollationMix(array_map(static fn ($domain): array => [$rules->operand($domain)[0]->name, $rules->operand($domain)[1]], $resolved), $operation);

        return throw new SqlError(match (count($domains)) {
            2 => DataError::CantAggregateTwoCollations,
            3 => DataError::CantAggregateThreeCollations,
            default => DataError::CantAggregateCollations,
        }, $mix->message());
    }
}
