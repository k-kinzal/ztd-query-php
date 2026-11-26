<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Rows\With;

use SqlSemantics\Platform\MySql\Ir\Rows\LocalProjection;
use SqlSemantics\Platform\MySql\Ir\Select;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;

/**
 * Owns visibility and the output-width rule at a WITH boundary.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class WithScope
{
    /**
     * Validates a member against the existing definition prefix.
     * @param list<int> $headings The visible widths in declaration order
     */
    public function width(Select|LocalProjection|WithProjection|WithRead $query, array $headings, string $profile): int
    {
        if ($query instanceof WithRead) {
            $width = $headings[$query->definition] ?? null;
            Check::input($width !== null, 'A local read must select a visible WITH definition.');
            foreach ($query->columns as $column) {
                Check::input($column->slot < $width, 'A local read must select an existing WITH output slot.');
            }
        } else {
            Check::input($query->profileId() === $profile, 'Each WITH query must use its owning language profile.');
            Check::input(!$query instanceof Select || count($query->columns) <= 1024, 'A WITH query can have at most 1024 result columns.');
            Check::input(!$query instanceof Select || ($query->orderBy === [] && $query->limit === null), 'An ordinary WITH projection needs unbounded unordered SELECT members.');
        }

        return $query instanceof WithProjection ? $query->width() : count($query->columns);
    }
}
