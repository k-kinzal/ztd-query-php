<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Prepared;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\PostgreSql\Rules\Manipulation\Placement;
use SqlSemantics\Platform\PostgreSql\Rules\Manipulation\Sources;
use SqlSemantics\Platform\PostgreSql\Rules\Query\Facts\QueryRoots;
use SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Modification;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Resolution\VisibleRelation;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

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
 * @visibility public
 * @example Reading a prepared statement
 *     $prepare = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('PREPARE p (integer) AS SELECT $1');
 *     [$prepare->statement->name->value, count($prepare->statement->parameters->types), $prepare->toString()] // => ['p', 1, 'PREPARE p (INTEGER) AS SELECT $1']
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
