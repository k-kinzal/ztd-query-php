<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Policy;

use SqlParser\Parser\Node;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Schema\Catalog;

/**
 * Lowers a complete parser input into immutable operations and declaration references.
 * @visibility SqlSemantics
 */
interface OperationRules
{
    /**
     * Parser objects are consumed here and cannot become part of the returned graph.
     */
    public function read(Node $source, Catalog $catalog): Operation;
}
