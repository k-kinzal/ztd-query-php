<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Construction;

use SqlSemantics\Statement\Query\Row;
use SqlSemantics\Statement\Query\Rows;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Relation\SqliteAliasScope;
use SqlSemantics\Statement\Schema\Catalog;

/**
 * Constructs each row in one fresh namespace, without importing bound columns.
 * @visibility SqlSemantics
 */
final class RowsConstruction
{
    /**
     * Unequal row widths are semantic SQL diagnostics, not a construction rejection.
     * @return ($context is Catalog ? Rows : \SqlSemantics\Statement\Query\ScopedRows)
     */
    public function derive(Query\RowsDefinition $input, Catalog|Scope|SqliteAliasScope $context): Rows|\SqlSemantics\Statement\Query\ScopedRows
    {
        return $context instanceof Catalog ? new Rows($context, $input) : new \SqlSemantics\Statement\Query\ScopedRows($context, $input);
    }
}
