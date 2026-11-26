<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Prepared;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\VisibleRelation;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Query;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Statement;
use SqlSemantics\Platform\PostgreSql\Rules\Manipulation\Placement;
use SqlSemantics\Platform\PostgreSql\Rules\Manipulation\Sources;
use SqlSemantics\Platform\PostgreSql\Rules\Query\Facts\QueryRoots;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Modification;

/**
 * PREPARE: a statement stored under a name in the session, with the types of its parameters.
 *
 * Mirrors PostgreSQL's `PrepareStmt` (name, argtypes, query). Rule:
 * PG-PREPARE-001. The statement is derived as a nested query in an
 * environment that sees only the declared parameter types
 * (PG-PREPARE-PARAMETERS-001), so `$n` has the declared type of its
 * position; preparing it returns no rows and declares no relation. A selection with INTO is admitted as the
 * first selection of a query statement only (PG-PLACEMENT-001).
 * Source: https://www.postgresql.org/docs/17/sql-prepare.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class Prepare implements Statement
{
    use Snapshot;

    /**
     * @param Name $name The name of the prepared statement
     * @param PreparedParameters|null $parameters The declared parameter types; null when none is written
     * @param Query $statement The statement prepared: a query statement or a data-modifying statement
     *
     * @throws InvalidConstruction When the statement is of another kind
     */
    public function __construct(public readonly Name $name, public readonly ?PreparedParameters $parameters, public readonly Query $statement)
    {
        Check::input((new Sources())->statement($statement) || $statement instanceof Modification, 'PREPARE prepares a query statement or a data-modifying statement.');
    }

    /**
     * Derives the types and the statement.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $environment = $derivation->environment();
        if ($this->parameters !== null) {
            $declared = $derivation->relation($this->parameters, $environment);
            $environment = new Environment($derivation->context, null, [new VisibleRelation($this->parameters, $declared->shape)]);
        }
        $derivation->query($this->statement, $environment);
        $placement = new Placement();
        $placement->values($this, [], [], $derivation);
        $placement->into($this, $derivation, $this->statement instanceof Modification ? null : (new QueryRoots())->first($this->statement));
        $placement->modifying($this, $this->statement, $derivation);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('PREPARE')->name($this->name, NameUse::Column);
        $out->node($this->parameters)->keyword('AS')->node($this->statement);
    }
}
