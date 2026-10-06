<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Fact;

use SqlSemantics\Statement\Reference\Column\Resolution;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Nullability;
use SqlSemantics\Statement\Type\TypeFact;

/**
 * What an operation derived for one scalar expression at its use position.
 *
 * @visibility public
 * @example Reading the facts of an expression
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT NULL');
 *     $query->facts->scalar($query->field(0)->expression)->nullability // => \SqlSemantics\Statement\Type\Nullability::Nullable
 */
final class ScalarFact
{
    use Snapshot;

    /**
     * @param TypeFact $type What is known about the type
     * @param Nullability $nullability Whether the value can be NULL
     * @param Resolution|null $resolution The name resolution when the expression is a name use
     */
    public function __construct(
        public readonly TypeFact $type,
        public readonly Nullability $nullability,
        public readonly ?Resolution $resolution = null,
    ) {
    }
}
