<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Column;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Node;

/**
 * One attribute of a column definition, such as NOT NULL, DEFAULT, COMMENT or CHECK.
 *
 * MySQL accepts the attributes in any order and applies them in written
 * order, so a column keeps them as a list.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html.
 */
interface ColumnAttribute extends Node
{
    /**
     * Derives the expressions inside the attribute at a position whose visible relation is the table being defined or changed.
     */
    public function deriveAttribute(Derivation $derivation, Environment $scope): void;
}
