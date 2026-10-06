<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Table\Column\ColumnAttribute;
use SqlSemantics\Platform\MySql\Statement\Type\TypeName;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Node;

/**
 * The definition of a column without its name: data type, attributes, generation expression and reference.
 *
 * The table definition family provides the structure; ALTER TABLE ... ADD, CHANGE
 * and MODIFY hold it.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table.html.
 */
interface ColumnSpecification extends Node
{
    /**
     * Answers the declared data type.
     */
    public function dataType(): TypeName;

    /**
     * Answers the column attributes in written order.
     *
     * @return list<ColumnAttribute>
     */
    public function columnAttributes(): array;

    /**
     * Derives the expressions inside the definition at a position whose visible relation is the table being defined or changed.
     */
    public function deriveSpecification(Derivation $derivation, Environment $scope): void;
}
