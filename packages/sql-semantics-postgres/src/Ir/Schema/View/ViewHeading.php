<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\View;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;

/**
 * Checks the ordered catalog heading shared by native persistent view definitions.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class ViewHeading
{
    /**
     * Validates the width and native case-sensitive name uniqueness.
     * @param non-empty-list<Identifier> $columns The checked, owned heading
     */
    public function check(array $columns, int $width): void
    {
        Check::input(count($columns) === $width && $width <= 1600, 'The view heading must match its query within 1600 columns.');
        $seen = [];
        foreach ($columns as $column) {
            Check::input(!isset($seen[$column->value]), 'A persistent view cannot repeat a column name.');
            $seen[$column->value] = true;
        }
    }
}
