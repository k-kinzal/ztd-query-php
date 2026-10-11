<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Fact;

use SqlSemantics\Statement\Reference\Column\Resolution;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Nullability;
use SqlSemantics\Statement\Type\TypeFact;

/**
 * What an operation derived for one scalar expression at its use position.
 *
 * A replacement names an already-bound strict descendant of the occurrence that the platform
 * evaluates in place of this occurrence. It preserves occurrence identity and bindings;
 * it is not SQL text for a consumer to parse or a change to the original statement structure.
 *
 * @visibility public
 * @example Reading the facts of an expression
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT NULL');
 *     $query->facts->scalar($query->field(0)->expression)->nullability // => \SqlSemantics\Statement\Type\Nullability::Nullable
 * @example Reading a scalar subquery reduction without rewriting its syntax
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT (SELECT 1 LIMIT 0)');
 *     $query->facts->scalar($query->field(0)->expression)->replacement instanceof \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral // => true
 */
final class ScalarFact
{
    use Snapshot;

    /**
     * @param TypeFact $type What is known about the type
     * @param Nullability $nullability Whether the value can be NULL
     * @param Resolution|null $resolution The name resolution when the expression is a name use
     * @param Scalar|null $replacement The bound expression evaluated in place of this occurrence, when resolution eliminates a wrapper
     */
    public function __construct(
        public readonly TypeFact $type,
        public readonly Nullability $nullability,
        public readonly ?Resolution $resolution = null,
        public readonly ?Scalar $replacement = null,
    ) {
    }
}
