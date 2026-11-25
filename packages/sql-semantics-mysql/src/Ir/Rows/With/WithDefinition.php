<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Rows\With;

use SqlSemantics\Platform\MySql\Ir\Rows\LocalProjection;
use SqlSemantics\Platform\MySql\Ir\Select;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * The body of a single ordinary WITH definition.
 *
 * @visibility public
 * @example Defining an independent local relation
 *     $field = new \SqlSemantics\Platform\MySql\Ir\ResultColumn(new \SqlSemantics\Platform\MySql\Ir\IntegerLiteral('1'), new \SqlSemantics\Platform\MySql\Ir\Identifier('n'));
 *     $select = new \SqlSemantics\Platform\MySql\Ir\Select([$field]);
 *     (new \SqlSemantics\Platform\MySql\Ir\Rows\With\WithDefinition($select))->body === $select // => true
 */
final class WithDefinition
{
    use Snapshot;

    /**
     * Fixes the admitted body of this declaration.
     */
    public function __construct(public readonly Select|LocalProjection|WithProjection|WithRead $body)
    {
    }
}
