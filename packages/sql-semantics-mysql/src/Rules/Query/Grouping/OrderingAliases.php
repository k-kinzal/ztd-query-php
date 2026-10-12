<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Query\Grouping;

use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Query\SortScopes;
use SqlSemantics\Platform\MySql\Statement\Name\AliasRule;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Name\InvalidProjectionAlias;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\OutputOrdinal;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Statement\Fact\Facts;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Reference\Column\AliasTarget;
use SqlSemantics\Statement\Scalar;

/**
 * Rejects legacy aggregate aliases embedded in sorting expressions.
 *
 * MySQL 5.6 and 5.7 allow a bare aggregate alias in ORDER BY, including parentheses,
 * but reject references to it inside another expression. From 8.0 those references
 * are accepted. Verified through Testcontainers SQL observations; the same legacy
 * behavior is documented with SQL examples at https://bugs.mysql.com/bug.php?id=80802.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class OrderingAliases
{
    /**
     * Answers the first embedded reference to an aggregate owned by this block.
     *
     * @param list<Scalar> $aggregates The resolved aggregate occurrences of the block
     */
    public function check(Select $select, Facts $facts, array $aggregates, GrammarRelease $release): ?InvalidProjectionAlias
    {
        if (!in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true) || $aggregates === []) {
            return null;
        }
        $owned = array_fill_keys(array_map(spl_object_id(...), $aggregates), true);
        foreach ([...$select->orderBy, ...($select->late->orderBy ?? [])] as $item) {
            if ($item->expression instanceof OutputOrdinal || (new SortScopes())->word($item->expression) !== null) {
                continue;
            }
            $problem = $this->nested($item->expression, $facts, $owned);
            if ($problem !== null) {
                return $problem;
            }
        }

        return null;
    }

    /**
     * Inspects references in this expression without entering a nested query block.
     *
     * @param array<int, true> $owned The aggregate occurrence identities
     */
    public function nested(Scalar $expression, Facts $facts, array $owned): ?InvalidProjectionAlias
    {
        $pending = [$expression];
        while ($pending !== []) {
            $node = array_pop($pending);
            if (is_array($node)) {
                array_push($pending, ...array_reverse(array_values($node)));
                continue;
            }
            if (!is_object($node) || $node instanceof Query) {
                continue;
            }
            if ($node instanceof ColumnUse && $facts->covers($node)) {
                $resolution = $facts->scalar($node)->resolution;
                $source = $resolution instanceof AliasTarget && $resolution->depth === 0 ? $resolution->field->expression : null;
                if ($source !== null && (new OrderingAggregates())->occurrences($source, $owned) !== []) {
                    return new InvalidProjectionAlias($node->name, AliasRule::Aggregate, [$source]);
                }
            }
            array_push($pending, ...array_reverse(array_values(get_object_vars($node))));
        }

        return null;
    }
}
