<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Model\Resolution;

use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Node;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Shape\RowShape;

/**
 * A common table expression visible at a position: its name, definition and output shape.
 *
 * @visibility SqlSemantics
 */
final class CommonBinding
{
    /**
     * @param Name $name The common table name
     * @param Node $definition The definition node of the statement
     * @param RowShape $shape The output shape a reference to the common table sees
     */
    public function __construct(public readonly Name $name, public readonly Node $definition, public readonly RowShape $shape)
    {
    }
}
