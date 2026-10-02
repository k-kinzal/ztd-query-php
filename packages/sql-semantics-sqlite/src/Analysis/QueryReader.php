<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Analysis;

use SqlParser\Parser\Node;
use SqlSemantics\Statement\Query\Rows;
use SqlSemantics\Statement\Query\Select;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Relation\SqliteAliasScope;
use SqlSemantics\Statement\Schema\Catalog;

/**
 * Distinguishes a row constructor from a SELECT over input relations.
 * @visibility SqlSemantics
 */
final class QueryReader
{
    /**
     * Preserves the query's source form as a concrete semantic type.
     * @return ($catalog is Catalog ? Select|Rows : \SqlSemantics\Statement\Query\ScopedSelect|\SqlSemantics\Statement\Query\ScopedRows)
     */
    public function read(Node $source, Catalog|Scope|SqliteAliasScope $catalog): Select|\SqlSemantics\Statement\Query\ScopedSelect|Rows|\SqlSemantics\Statement\Query\ScopedRows
    {
        $input = (new Input\QueryInputReader())->read($source);
        if ($input instanceof \SqlSemantics\Statement\Construction\Query\RowsDefinition) {
            return (new \SqlSemantics\Statement\Construction\RowsConstruction())->derive($input, $catalog);
        }
        return $catalog instanceof Catalog ? new Select($catalog, $input) : new \SqlSemantics\Statement\Query\ScopedSelect($catalog, $input);
    }
}
