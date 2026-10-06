<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rules\Table\Command;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Index\ColumnKey;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Index\ExpressionKey;

/**
 * Checks the key terms of extended statistics.
 *
 * Rule: PG-KEY-TERMS-001. A key term is a column written by name or an
 * expression; there is at least one. Source:
 * https://www.postgresql.org/docs/17/sql-createstatistics.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class KeyTerms
{
    /**
     * Checks a list of key terms.
     *
     * @param array<array-key, object|scalar|null> $terms
     * @return non-empty-list<ColumnKey|ExpressionKey>
     */
    public function checked(array $terms): array
    {
        $checked = [];
        foreach (Check::listOf($terms, \SqlSemantics\Statement\Node::class, 'Key terms are an ordered list.') as $term) {
            Check::input($term instanceof ColumnKey || $term instanceof ExpressionKey, 'A key term is a column or an expression.');
            $checked[] = $term;
        }
        Check::input($checked !== [], 'There is at least one key term.');

        return $checked;
    }
}
