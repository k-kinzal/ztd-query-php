<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Partition\Bound;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Statement\Node;

/**
 * The partition values of a RANGE or LIST partition definition: `VALUES LESS THAN …` or `VALUES IN …`.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/partitioning-types.html.
 */
interface PartitionBound extends Node
{
    /**
     * Derives the values as constants: they see no column.
     */
    public function deriveBound(Derivation $derivation): void;
}
