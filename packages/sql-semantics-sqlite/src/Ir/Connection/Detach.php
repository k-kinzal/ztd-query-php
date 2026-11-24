<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Connection;

use SqlSemantics\Platform\Sqlite\Ir\Parameter;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\ExpressionScope;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * An SQLite detachment with a resolved or explicitly parameterized selector.
 *
 * @visibility public
 * @example Supplying the database name at execution time
 *     $name = new \SqlSemantics\Platform\Sqlite\Ir\Parameter('03');
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Connection\Detach($name))->toString() // => 'DETACH ?03'
 */
final class Detach implements SemanticsIR
{
    use Snapshot;

    /**
     * Checks the target's complete parameter or literal payload.
     */
    public function __construct(public readonly DetachTarget|Parameter $target)
    {
        $value = $target instanceof DetachTarget ? $target->name->literal : $target;
        (new ExpressionScope())->check([], [[$value, 0, 0]]);
    }

    /**
     * Identifies the pinned SQLite language profile.
     */
    public function profileId(): string
    {
        return 'sqlite-3.47.2/utf8/short-column-names/numbered-parameters';
    }

    /**
     * Emits the resolved name operand or native numbered marker.
     */
    public function toString(): string
    {
        $name = $this->target instanceof DetachTarget ? $this->target->name->toString() : '?' . $this->target->label;

        return 'DETACH ' . $name;
    }
}
