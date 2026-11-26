<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Rows\With;

use SqlSemantics\Platform\PostgreSql\Ir\Rows\LocalProjection;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\Values\ValuesQuery;
use SqlSemantics\Platform\PostgreSql\Ir\Select;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * A query and its policy in a single ordinary WITH definition.
 *
 * @visibility public
 * @example Defining an independent local relation
 *     $field = new \SqlSemantics\Platform\PostgreSql\Ir\ResultColumn(new \SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral('1'), new \SqlSemantics\Platform\PostgreSql\Ir\Identifier('n'));
 *     $select = new \SqlSemantics\Platform\PostgreSql\Ir\Select([$field]);
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\Rows\With\WithDefinition($select))->body === $select // => true
 */
final class WithDefinition
{
    use Snapshot;

    /**
     * Fixes the admitted body and materialization policy.
     */
    public function __construct(
        public readonly Select|LocalProjection|ValuesQuery|WithProjection|WithRead $body,
        public readonly WithMaterialization $materialization = WithMaterialization::Default,
    ) {
    }
}
