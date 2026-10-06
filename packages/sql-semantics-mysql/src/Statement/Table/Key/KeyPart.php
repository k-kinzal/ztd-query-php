<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Key;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Node;

/**
 * One part of an index or foreign key: a column with an optional prefix length, or an expression.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-index.html.
 */
interface KeyPart extends Node
{
    /**
     * Derives the expression of the part at a position whose visible relation is the indexed table.
     */
    public function deriveKeyPart(Derivation $derivation, Environment $scope): void;
}
