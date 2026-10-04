<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Query\Clause;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\WindowSpecification;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * One named window of a WINDOW clause.
 *
 * Mirrors a named PostgreSQL `WindowDef`. Its expressions see the input
 * columns of the selection that holds it.
 * Source: https://www.postgresql.org/docs/17/sql-select.html#SQL-WINDOW.
 *
 * @visibility public
 * @example Reading a named window
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT 1 WINDOW w AS ()');
 *     $query->statement->windows[0]->name->value // => 'w'
 */
final class WindowDefinition implements Clause
{
    use Snapshot;

    /**
     * @param Name $name The window name
     * @param WindowSpecification $window The window it names
     */
    public function __construct(public readonly Name $name, public readonly WindowSpecification $window)
    {
    }

    /**
     * Derives the expressions of the window.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        $this->window->deriveClause($derivation, $environment);
    }

    /**
     * Writes the name, AS and the specification.
     */
    public function render(Output $out): void
    {
        $out->name($this->name, NameUse::Column)->keyword('AS')->node($this->window);
    }
}
