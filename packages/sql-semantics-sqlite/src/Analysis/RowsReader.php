<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Analysis;

use SqlParser\Parser\Node;
use SqlSemantics\Statement\Query\Row;
use SqlSemantics\Statement\Query\Rows;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Relation\SqliteAliasScope;
use SqlSemantics\Statement\Schema\Catalog;

/**
 * Lowers explicit input rows into scoped expressions without evaluating them.
 * @visibility SqlSemantics
 */
final class RowsReader
{
    /**
     * An explicit row has no table scan; its expressions do not resolve against insertion destinations.
     * @return ($catalog is Catalog ? Rows : \SqlSemantics\Statement\Query\ScopedRows)
     */
    public function read(Node $source, Catalog|Scope|SqliteAliasScope $catalog): Rows|\SqlSemantics\Statement\Query\ScopedRows
    {
        $input = (new Input\QueryInputReader())->rows($source);
        return (new \SqlSemantics\Statement\Construction\RowsConstruction())->derive($input, $catalog);
    }
}
