<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Alter\Problem;

use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredAlterTable;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A complete alteration whose retained declaration has no ordinary statement role.
 *
 * @visibility public
 * @example Reporting the retained declaration role
 *     (new \ReflectionClass(\SqlSemantics\Platform\MySql\Statement\Alter\Problem\RefusedAlterTemplate::class))->implementsInterface(\SqlSemantics\Statement\Fact\Diagnostic::class) // => true
 */
final class RefusedAlterTemplate implements Diagnostic
{
    use Snapshot;

    /**
     * @param DeferredAlterTable $definition The complete target and actions
     */
    public function __construct(public readonly DeferredAlterTable $definition)
    {
    }

    /**
     * Describes the retained declaration independently of current catalog state.
     */
    public function message(): string
    {
        return 'The alteration retains a generation declaration with an invocation-time condition.';
    }
}
