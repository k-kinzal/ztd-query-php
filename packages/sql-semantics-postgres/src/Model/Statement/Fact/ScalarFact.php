<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Model\Statement\Fact;

use SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Column\Resolution;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\TypeFact;

/**
 * What an operation derived for one scalar expression at its use position.
 *
 * @visibility SqlSemantics
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
