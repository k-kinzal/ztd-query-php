<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Namespace\View;

use SqlSemantics\Platform\PostgreSql\Ir\Expression\TextCollation;
use SqlSemantics\Platform\PostgreSql\Ir\From\ColumnKind;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\View\CreateView;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;

/**
 * Checks the physical prefix that PostgreSQL retains on replacement.
 *
 * @visibility SqlSemantics
 */
final class SchemaViewReplacement
{
    /**
     * Checks that the new definition extends the established field signatures in order.
     *
     * @param array<array-key, array{ColumnKind, TextCollation|array{string,string,string,string}|null}> $established
     * @param array<array-key, array{ColumnKind, TextCollation|array{string,string,string,string}|null}> $updated
     */
    public static function check(CreateView $original, CreateView $replacement, array $established, array $updated): void
    {
        Check::input(count($replacement->columns) >= count($original->columns), 'A schema view replacement cannot remove established fields.');
        foreach ($original->columns as $position => $name) {
            $field = $name->value;
            Check::input($replacement->columns[$position]->value === $field, 'A schema view replacement cannot rename established fields.');
            Check::input($established[$field][0] === $updated[$field][0], 'A schema view replacement cannot change established native types.');
            Check::input($established[$field][1] === $updated[$field][1], 'A schema view replacement requires the same established collations.');
        }
    }
}
