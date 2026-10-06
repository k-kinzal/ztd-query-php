<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Reference\Missing;

use SqlSemantics\Statement\Snapshot;

/**
 * Connection state the context does not fix, such as a user variable or a server setting.
 *
 * @visibility public
 * @example Naming session state a fact depends on
 *     (new \SqlSemantics\Statement\Reference\Missing\SessionState('user variable @total'))->describe() // => 'the session state: user variable @total'
 */
final class SessionState implements MissingInput
{
    use Snapshot;

    /**
     * @param string $subject What part of the session state is needed
     */
    public function __construct(public readonly string $subject)
    {
    }

    /**
     * Describes the missing state.
     */
    public function describe(): string
    {
        return 'the session state: ' . $this->subject;
    }
}
