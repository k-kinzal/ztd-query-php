<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Query\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Snapshot;

/**
 * A query MySQL rejects because it breaks a rule of the language.
 *
 * @visibility public
 * @example Reading the problem of a star without input
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT *');
 *     $query->facts->diagnostics[0]->message() // => 'No tables used'
 */
final class Misuse implements Diagnostic
{
    use Snapshot;

    /**
     * @param MisuseRule $rule The broken rule
     * @param Name|QualifiedName|null $name The name the rule is broken for, as the server names it: the alias, common table, column, window or locked table
     */
    public function __construct(public readonly MisuseRule $rule, public readonly Name|QualifiedName|null $name = null)
    {
    }

    /**
     * Describes the broken rule in the words of the server.
     */
    public function message(): string
    {
        return $this->rule->value;
    }
}
