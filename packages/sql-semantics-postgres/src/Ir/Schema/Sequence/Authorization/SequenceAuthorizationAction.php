<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Authorization;

use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\SequenceLogging;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * One typed action in an ordered sequence authorization change.
 * @visibility public
 * @example Requesting a logging assignment in an ownership batch
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Authorization\SequenceAuthorizationAction(\SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\Alter\SequenceLogging::Logged))->toString() // => 'SET LOGGED'
 */
final class SequenceAuthorizationAction
{
    use Snapshot;

    /**
     * Selects exactly one native action.
     */
    public function __construct(public readonly SequenceLogging|SequencePrincipal $value)
    {
    }

    /**
     * Emits the selected action.
     */
    public function toString(): string
    {
        return $this->value instanceof SequenceLogging ? 'SET ' . $this->value->value : $this->value->toString();
    }
}
