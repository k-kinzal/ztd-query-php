<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation;

use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\Index\DeferredCreateIndex;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A complete index request whose retained key operand has no ordinary statement role.
 *
 * @visibility public
 * @example Reporting the retained key operand role
 *     (new \ReflectionClass(\SqlSemantics\Platform\MySql\Statement\Table\Problem\Creation\RefusedIndexTemplate::class))->implementsInterface(\SqlSemantics\Statement\Fact\Diagnostic::class) // => true
 */
final class RefusedIndexTemplate implements Diagnostic
{
    use Snapshot;

    /**
     * @param DeferredCreateIndex $definition The complete target and key operands
     */
    public function __construct(public readonly DeferredCreateIndex $definition)
    {
    }

    /**
     * Describes the retained key operand independently of current catalog state.
     */
    public function message(): string
    {
        return 'The index retains an operand with an invocation-time condition.';
    }
}
