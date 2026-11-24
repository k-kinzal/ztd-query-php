<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree;

use SqlSemantics\Platform\PostgreSql\Ir\From\ColumnKind;
use SqlSemantics\Platform\PostgreSql\Ir\TableSource;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;

/**
 * Checks and renders the closed partial-index condition against its owner.
 *
 * @visibility SqlSemantics
 */
final class IndexCondition
{
    /**
     * Checks all leaf slots at the complete index boundary.
     */
    public static function check(TableSource $table, NullIndexPredicate|IndexTruthTest|IndexBooleanPredicate $predicate): void
    {
        $pending = [$predicate];
        while ($pending !== []) {
            $node = array_pop($pending);
            if ($node instanceof NullIndexPredicate) {
                Check::input(isset($table->columns[$node->slot]), 'A partial B-tree predicate must refer to a column of the indexed table.');
            } elseif ($node instanceof IndexTruthTest) {
                Check::input(($table->columnKinds[$node->slot] ?? null) === ColumnKind::Boolean, 'An index truth test needs a Boolean column of the indexed table.');
            } else {
                $pending[] = $node->left;
                if ($node->right !== null) {
                    $pending[] = $node->right;
                }
            }
        }
    }

    /**
     * Emits an explicitly grouped WHERE clause from the checked roles.
     */
    public static function sql(TableSource $table, NullIndexPredicate|IndexTruthTest|IndexBooleanPredicate $predicate): string
    {
        $pending = [$predicate];
        $sql = ' WHERE ';
        while ($pending !== []) {
            $node = array_pop($pending);
            if (is_string($node)) {
                $sql .= $node;
            } elseif ($node instanceof NullIndexPredicate) {
                $sql .= '(' . $table->columns[$node->slot]->quoted() . ($node->negated ? ' IS NOT NULL)' : ' IS NULL)');
            } elseif ($node instanceof IndexTruthTest) {
                $sql .= '(' . $table->columns[$node->slot]->quoted() . ' ' . $node->operator->value . ')';
            } elseif ($node->right === null) {
                $sql .= '(' . $node->operator->value . ' ';
                $pending[] = ')';
                $pending[] = $node->left;
            } else {
                $sql .= '(';
                $pending[] = ')';
                $pending[] = $node->right;
                $pending[] = ' ' . $node->operator->value . ' ';
                $pending[] = $node->left;
            }
        }

        return $sql;
    }
}
