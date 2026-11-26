<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree;

use SqlSemantics\Platform\PostgreSql\Ir\From\ColumnKind;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\Binding\IndexField;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Declaration\IndexHeading;

/**
 * Checks and renders the closed partial-index condition against its owner.
 *
 * @visibility SqlSemantics
 */
final class IndexCondition
{
    /**
     * Checks and projects all leaf slots at the complete index boundary.
     * @return array<int, IndexField>
     */
    public static function bindings(
        IndexHeading $heading,
        NullIndexPredicate|IndexTruthTest|IndexBooleanColumn|IndexBooleanPredicate $predicate,
    ): array {
        $pending = [$predicate];
        $fields = [];
        while ($pending !== []) {
            $node = array_pop($pending);
            if ($node instanceof NullIndexPredicate) {
                Check::input(isset($heading->columns[$node->slot]), 'A partial B-tree predicate must refer to a column of the indexed table.');
                $fields[$node->slot] = $heading->field($node->slot);
            } elseif ($node instanceof IndexTruthTest || $node instanceof IndexBooleanColumn) {
                Check::input(($heading->columnKinds[$node->slot] ?? null) === ColumnKind::Boolean, 'A partial index Boolean operand needs a Boolean column of the indexed table.');
                $fields[$node->slot] = $heading->field($node->slot);
            } else {
                $pending[] = $node->left;
                if ($node->right !== null) {
                    $pending[] = $node->right;
                }
            }
        }

        return $fields;
    }

    /**
     * Emits an explicitly grouped WHERE clause from the checked roles.
     * @param array<int, IndexField> $fields The complete leaf binding subset
     */
    public static function sql(
        array $fields,
        NullIndexPredicate|IndexTruthTest|IndexBooleanColumn|IndexBooleanPredicate $predicate,
    ): string {
        $pending = [$predicate];
        $sql = ' WHERE ';
        while ($pending !== []) {
            $node = array_pop($pending);
            if (is_string($node)) {
                $sql .= $node;
            } elseif ($node instanceof NullIndexPredicate) {
                $sql .= '(' . $fields[$node->slot]->name->quoted() . ($node->negated ? ' IS NOT NULL)' : ' IS NULL)');
            } elseif ($node instanceof IndexTruthTest) {
                $sql .= '(' . $fields[$node->slot]->name->quoted() . ' ' . $node->operator->value . ')';
            } elseif ($node instanceof IndexBooleanColumn) {
                $sql .= '(' . $fields[$node->slot]->name->quoted() . ')';
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
