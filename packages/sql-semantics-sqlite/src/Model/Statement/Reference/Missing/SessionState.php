<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Missing;

use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * Connection state the context does not fix, such as a user variable or a server setting.
 *
 * @visibility SqlSemantics
 * @example Naming session state a fact depends on
 *     (new \SqlSemantics\Platform\Sqlite\Model\Statement\Reference\Missing\SessionState('user variable @total'))->describe() // => 'the session state: user variable @total'
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
