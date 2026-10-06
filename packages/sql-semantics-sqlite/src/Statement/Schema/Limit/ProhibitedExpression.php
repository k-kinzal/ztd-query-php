<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema\Limit;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * An expression of a definition that uses a construct SQLite rejects at its position.
 *
 * SQLite reports "parameters prohibited in CHECK constraints" and the like
 * when the definition is prepared.
 * Source: https://sqlite.org/lang_createtable.html#check_constraints,
 * https://sqlite.org/partialindex.html, https://sqlite.org/expridx.html,
 * https://sqlite.org/gencol.html.
 *
 * @visibility public
 * @example Reporting a subquery in a partial index condition
 *     $index = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('CREATE INDEX i ON t (a) WHERE a IN (SELECT 1)');
 *     $index->facts->diagnostics[0]->message() // => 'Subqueries are prohibited in partial index WHERE clauses.'
 */
final class ProhibitedExpression implements Diagnostic
{
    use Snapshot;

    /**
     * @param ProhibitedConstruct $construct What the expression uses
     * @param DefinitionPosition $position Where it is used
     */
    public function __construct(public readonly ProhibitedConstruct $construct, public readonly DefinitionPosition $position)
    {
    }

    /**
     * Describes the problem.
     */
    public function message(): string
    {
        $construct = $this->construct === ProhibitedConstruct::DotOperator ? 'The "." operator is' : ucfirst($this->construct->value) . ' are';

        return $construct . ' prohibited in ' . $this->position->value . '.';
    }
}
