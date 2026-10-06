<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Query\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * A call of a built-in function with a number of arguments the function does not accept.
 *
 * @visibility public
 * @example Reading the problem of a call with too many arguments
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT abs(1, 2)');
 *     $query->facts->diagnostics[0]->message() // => 'wrong number of arguments to function abs()'
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
