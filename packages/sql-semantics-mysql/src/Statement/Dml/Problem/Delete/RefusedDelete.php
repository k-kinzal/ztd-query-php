<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml\Problem\Delete;

use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredDelete;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredMultipleDelete;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A standalone deletion whose target role belongs to a stored instruction template.
 *
 * @visibility public
 * @example Rejecting a read-only target before publishing an ordinary statement
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('DELETE q FROM (SELECT 1) q') // throws \SqlSemantics\Construction\RejectedInput
 */
final class RefusedDelete implements Diagnostic
{
    use Snapshot;

    /**
     * @param DeferredDelete|DeferredMultipleDelete $write The complete retained deletion
     */
    public function __construct(public readonly DeferredDelete|DeferredMultipleDelete $write)
    {
    }

    /**
     * Describes the target-role constraint; dependency conditions may precede it.
     */
    public function message(): string
    {
        return 'An intrinsically read-only relation cannot supply a DELETE target.';
    }
}
