<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Analysis;

use SqlParser\Parser\Node;
use SqlSemantics\Core\Ast\Tree;
use SqlSemantics\Statement\Query\Rows;
use SqlSemantics\Statement\Query\Select;
use SqlSemantics\Statement\Schema\Catalog;

/**
 * Distinguishes a row constructor from a SELECT over input relations.
 * @visibility SqlSemantics
 */
final class QueryReader
{
    /**
     * Preserves the query's source form as a concrete semantic type.
     */
    public function read(Node $source, Catalog $catalog): Select|Rows
    {
        Tree::assertChildren($source, ['selectnowith'], []);
        $body = Tree::child($source, ['selectnowith']);
        assert($body !== null, 'A query has a query body.');
        Tree::assertChildren($body, ['oneselect'], []);
        $single = Tree::child($body, ['oneselect']);
        assert($single !== null, 'A simple query has one query operation.');
        $rows = Tree::child($single, ['values', 'mvalues']);
        if ($rows !== null) {
            Tree::assertChildren($single, ['values', 'mvalues'], []);
            return (new RowsReader())->read($rows, $catalog);
        }
        return (new SelectReader())->read($source, $catalog);
    }
}
