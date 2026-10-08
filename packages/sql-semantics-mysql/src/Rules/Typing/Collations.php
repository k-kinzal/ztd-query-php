<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Typing;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\IllegalCollationMix;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * Decides the collation of an operation on strings from the collations and coercibility of its operands.
 *
 * The operand whose collation holds most strongly wins. Two different collations that hold
 * equally strongly settle as follows: explicit collations conflict; the binary collation wins;
 * within one character set a `_bin` collation wins over the others, and two others give the
 * `_bin` collation of the set with no coercibility (NONE); across character sets a Unicode set
 * wins over a non-Unicode one, utf8mb4 wins over the other Unicode sets as it holds every
 * character they do, and otherwise they conflict. A comparison needs a determinate
 * collation: a result of no coercibility conflicts there.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/charset-collation-coercibility.html.
 *
 * @visibility public
 * @example Settling a column collation against a literal
 *     $rules = new \SqlSemantics\Platform\MySql\Rules\Typing\Collations(\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::known('utf8mb4_0900_ai_ci'));
 *     $column = \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain::string(5, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::known('latin1_bin'));
 *     $rules->settle([$column, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain::string(1, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::known('utf8mb4_0900_ai_ci'), \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field::VarString, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility::Coercible)])[0]->name // => 'latin1_bin'
 */
final class Collations
{
    private const UNICODE = ['utf8mb4', 'utf8mb3', 'utf16', 'utf16le', 'utf32', 'ucs2'];

    /**
     * @param Collation $connection The collation a value that is not a string is written in
     */
    public function __construct(public readonly Collation $connection)
    {
    }

    /**
     * Answers the collation and coercibility of an operation, or null after reporting a conflict.
     *
     * @param list<Domain> $domains The operands
     * @param string $operation The operation as the server names it in the message
     * @param bool $comparison Whether the operation compares, and so needs a determinate collation
     * @return array{Collation, Coercibility}|null
     */
    public function aggregate(array $domains, string $operation, Derivation $derivation, bool $comparison = false): ?array
    {
        $settled = $this->settle($domains, $comparison);
        if ($settled === null) {
            $derivation->report(new IllegalCollationMix(array_map(fn (Domain $domain): array => [$this->operand($domain)[0]->name, $this->operand($domain)[1]], $domains), $operation));
        }

        return $settled;
    }

    /**
     * Answers the collation and coercibility of an operation, or null when the collations conflict.
     *
     * @param list<Domain> $domains The operands
     * @param bool $comparison Whether the operation compares, and so needs a determinate collation
     * @return array{Collation, Coercibility}|null
     */
    public function settle(array $domains, bool $comparison = false): ?array
    {
        $collation = null;
        $level = Coercibility::Ignorable;
        foreach ($domains as $domain) {
            [$candidate, $candidateLevel] = $this->operand($domain);
            if ($collation === null || $candidateLevel->value < $level->value) {
                [$collation, $level] = [$candidate, $candidateLevel];
                continue;
            }
            if ($candidateLevel !== $level || $candidate === $collation) {
                continue;
            }
            $tie = $this->tie($collation, $candidate, $level);
            if ($tie === null) {
                return null;
            }
            [$collation, $level] = $tie;
        }

        return $comparison && $level === Coercibility::None ? null : [$collation ?? $this->connection, $level];
    }

    /**
     * Answers the collation and coercibility one operand brings.
     *
     * @return array{Collation, Coercibility}
     */
    public function operand(Domain $domain): array
    {
        $text = $domain->kind === Kind::String || $domain->kind === Kind::Json;

        return [$text ? $domain->collation : $this->connection, $domain->kind === Kind::Null ? Coercibility::Ignorable : ($text ? $domain->coercibility : Coercibility::Numeric)];
    }

    /**
     * Settles two different collations that hold equally strongly, or answers null when they conflict.
     *
     * @return array{Collation, Coercibility}|null
     */
    public function tie(Collation $left, Collation $right, Coercibility $level): ?array
    {
        if ($level === Coercibility::Explicit) {
            return null;
        }
        if ($left->bytes() || $right->bytes()) {
            return [Collation::binary(), $level];
        }
        if ($left->charset === $right->charset) {
            if ($left->binaryOrder() !== $right->binaryOrder()) {
                return [$left->binaryOrder() ? $left : $right, $level];
            }
            $bin = Collation::named($left->charset->name . '_bin');

            return $bin === null ? null : [$bin, Coercibility::None];
        }
        $leftUnicode = in_array($left->charset->name, self::UNICODE, true);
        $rightUnicode = in_array($right->charset->name, self::UNICODE, true);
        if ($leftUnicode && $rightUnicode && ($left->charset->name === 'utf8mb4' || $right->charset->name === 'utf8mb4')) {
            return [$left->charset->name === 'utf8mb4' ? $left : $right, $level];
        }
        if ($leftUnicode === $rightUnicode) {
            return null;
        }

        return [$leftUnicode ? $left : $right, $level];
    }

}
