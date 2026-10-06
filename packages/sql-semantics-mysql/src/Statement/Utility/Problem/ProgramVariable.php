<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Utility\Problem;

use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Reference\Missing\MissingInput;
use SqlSemantics\Statement\Snapshot;

/**
 * The declarations of the stored program a SET runs in, which decide whether a name is one of its variables.
 *
 * Inside a stored program, `SET x = v` assigns the declared variable `x` when
 * the program declares one and the system variable `x` otherwise; the value
 * `v` of a system variable that is a bare name is its text, while for a
 * program variable it is an expression. A version 1 context holds no
 * program declarations.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/set-variable.html,
 * https://dev.mysql.com/doc/refman/8.4/en/local-variable-scope.html.
 *
 * @visibility public
 * @example Describing the missing declarations
 *     (new \SqlSemantics\Platform\MySql\Statement\Utility\Problem\ProgramVariable(new \SqlSemantics\Statement\Identifier\Name('x')))->describe() // => 'the variable declarations of the enclosing stored program, which decide what x denotes'
 */
final class ProgramVariable implements MissingInput
{
    use Snapshot;

    /**
     * @param Name $name The name whose meaning the declarations decide
     */
    public function __construct(public readonly Name $name)
    {
    }

    /**
     * Describes the missing declarations.
     */
    public function describe(): string
    {
        return 'the variable declarations of the enclosing stored program, which decide what ' . $this->name->value . ' denotes';
    }
}
