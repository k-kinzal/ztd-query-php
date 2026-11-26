<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Query\Problem;

use SqlSemantics\Platform\Sqlite\Model\Statement\Fact\Diagnostic;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;

/**
 * A call of a built-in function with a number of arguments the function does not accept.
 *
 * @visibility SqlSemantics
 */
final class WrongArgumentCount implements Diagnostic
{
    use Snapshot;

    /**
     * @param Name $function The function name as written
     * @param int $arguments The number of arguments supplied
     */
    public function __construct(public readonly Name $function, public readonly int $arguments)
    {
    }

    /**
     * Describes the problem in the words of SQLite.
     */
    public function message(): string
    {
        return 'wrong number of arguments to function ' . $this->function->value . '()';
    }
}
