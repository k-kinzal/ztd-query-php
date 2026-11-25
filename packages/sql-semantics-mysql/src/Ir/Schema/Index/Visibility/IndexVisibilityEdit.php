<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\Index\Visibility;

use SqlSemantics\Platform\MySql\Ir\Identifier;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\NativeIdentifierCase;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * An ordered optimizer visibility assignment on a resolved native secondary index.
 * @visibility public
 * @example Hiding a secondary unique index from the optimizer
 *     $name = new \SqlSemantics\Platform\MySql\Ir\Identifier('by_name');
 *     $kind = \SqlSemantics\Platform\MySql\Ir\Schema\Index\Visibility\SecondaryIndexKind::Unique;
 *     (new \SqlSemantics\Platform\MySql\Ir\Schema\Index\Visibility\IndexVisibilityEdit($name, $kind, false))->toString() // => 'ALTER INDEX `by_name` INVISIBLE'
 */
final class IndexVisibilityEdit
{
    use Snapshot;

    /**
     * Checks that the native operand can describe a secondary index on its owner.
     */
    public function __construct(
        public readonly Identifier $index,
        public readonly SecondaryIndexKind $kind,
        public readonly bool $visible,
    ) {
        Check::input(preg_match('/\A.{1,64}(?<! )\z/us', $index->value) === 1, 'An index name has 1 to 64 characters and no trailing space.');
        Check::input((new NativeIdentifierCase())->fold($index->value) !== 'primary', 'Native index visibility requires a secondary key.');
    }

    /**
     * Emits one native index visibility assignment.
     */
    public function toString(): string
    {
        return 'ALTER INDEX ' . $this->index->quoted() . ($this->visible ? ' VISIBLE' : ' INVISIBLE');
    }
}
