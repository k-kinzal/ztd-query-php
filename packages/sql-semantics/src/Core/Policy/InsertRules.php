<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Policy;

use SqlParser\Parser\Node;
use SqlSemantics\Core\Analysis\InsertReader;
use SqlSemantics\Semantic\Statement\InsertRows;
use SqlSemantics\Semantic\Statement\InsertSelect;

/**
 * Recognizes complete insertion forms at the dialect boundary.
 * @visibility SqlSemantics
 */
interface InsertRules
{
    /**
     * Lowers the supplied syntax and rejects any unmodeled semantic operation.
     */
    public function read(Node $statement, InsertReader $reader): InsertRows|InsertSelect;
}
