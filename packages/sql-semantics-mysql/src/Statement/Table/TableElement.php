<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Node;

/**
 * One element of a table definition: a column, an index, or a constraint.
 *
 * The table definition family provides the structures; ALTER TABLE ... ADD holds
 * them.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html.
 */
interface TableElement extends Node
{
    /**
     * Derives the expressions inside the element at a position whose visible relation is the table being defined or changed.
     */
    public function deriveElement(Derivation $derivation, Environment $scope): void;
}
