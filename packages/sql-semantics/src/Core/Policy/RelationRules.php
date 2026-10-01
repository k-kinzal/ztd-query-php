<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Policy;

use SqlParser\Parser\Node;
use SqlSemantics\Core\Analysis\RelationReader;
use SqlSemantics\Semantic\Relation\Join;
use SqlSemantics\Semantic\Relation\TableReference;

/**
 * Lowers dialect relation syntax into semantic operations.
 * @visibility SqlSemantics
 */
interface RelationRules
{
    /**
     * Lowers one named table or joined relation while preserving occurrence identity.
     */
    public function relation(Node $node, RelationReader $reader): TableReference|Join;
}
