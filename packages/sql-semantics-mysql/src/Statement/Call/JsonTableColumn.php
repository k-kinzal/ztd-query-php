<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Shape\OutputSlot;

/**
 * One column of the COLUMNS clause of JSON_TABLE: an ordinality, path, EXISTS or NESTED column.
 *
 * The function-like expression family provides the structure; the query family
 * holds the columns in the JSON_TABLE relation and builds its row shape from
 * deriveColumns().
 * Source: https://dev.mysql.com/doc/refman/8.4/en/json-table-functions.html.
 *
 * @visibility public
 * @example Reading the output a column contributes
 *     $column = new \SqlSemantics\Platform\MySql\Statement\Call\Json\OrdinalityColumn(new \SqlSemantics\Statement\Identifier\Name('n'));
 *     $column->name->value // => 'n'
 */
interface JsonTableColumn extends Node
{
    /**
     * Derives the expressions the column holds and answers the output slots it contributes, in order, nested columns flattened.
     *
     * @return list<OutputSlot>
     */
    public function deriveColumns(Derivation $derivation, Environment $environment): array;
}
