<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Expression\Shape;

use SqlSemantics\Platform\MySql\Statement\Query;

/**
 * The output width fixed by a query's structure, before catalog resolution.
 *
 * Stars over derived tables, common tables and JSON_TABLE retain fixed widths.
 * A catalog relation leaves its width dependent on declarations. An explicit
 * projection fixes its width even when column names are unresolved.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class QueryWidth
{
    /**
     * Answers the fixed width, or null when the output depends on the catalog.
     *
     * @param array<string, list<\SqlSemantics\Statement\Identifier\Name|null>|null> $common Visible common-table outputs
     */
    public function of(\SqlSemantics\Statement\Query|Query\Set\LeadingUnion $query, bool $exists = false, array $common = []): ?int
    {
        $columns = (new QueryColumns())->of($query, $exists, $common);
        return $columns === null ? null : count($columns);
    }
}
