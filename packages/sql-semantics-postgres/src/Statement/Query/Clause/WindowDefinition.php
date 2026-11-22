<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Query\Clause;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\WindowSpecification;

/**
 * One named window of a WINDOW clause.
 *
 * Mirrors a named PostgreSQL `WindowDef`. Its expressions see the input
 * columns of the selection that holds it.
 * Source: https://www.postgresql.org/docs/17/sql-select.html#SQL-WINDOW.
 *
 * @visibility SqlSemantics
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
