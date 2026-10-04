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
use SqlSemantics\Platform\PostgreSql\Statement\Type\TypeName;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * PREPARE: a statement stored under a name in the session, with the types of its parameters.
 *
 * Mirrors PostgreSQL's `PrepareStmt` (name, argtypes, query). Rule:
 * PG-PREPARE-001. The statement is derived as a nested query in the empty
 * environment; preparing it returns no rows and declares no relation. The
 * declared types are derived; the parameters `$n` of the statement remain
 * dependent on the session, because a parameter type cannot be supplied to
 * the derivation of the statement. A selection with INTO is admitted as the
 * first selection of a query statement only (PG-PLACEMENT-001).
 * Source: https://www.postgresql.org/docs/17/sql-prepare.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading a prepared statement
 *     $prepare = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('PREPARE p (integer) AS SELECT $1');
 *     [$prepare->statement->name->value, count($prepare->statement->types), $prepare->toString()] // => ['p', 1, 'PREPARE p (INTEGER) AS SELECT $1']
 */
final class Prepare implements Statement
{
    use Snapshot;

    /**
     * @var list<TypeName> The declared parameter types in order
     */
    public readonly array $types;

    /**
     * @param Name $name The name of the prepared statement
     * @param list<TypeName> $types The declared parameter types in order
     * @param Query $statement The statement prepared: a query statement or a data-modifying statement
     *
     * @throws InvalidConstruction When the statement is of another kind, or a type is not a type name
     */
    public function __construct(public readonly Name $name, array $types, public readonly Query $statement)
    {
        $this->types = Check::listOf($types, TypeName::class, 'The parameter types of PREPARE are type names.');
        Check::input((new Sources())->statement($statement) || $statement instanceof Modification, 'PREPARE prepares a query statement or a data-modifying statement.');
    }

    /**
     * Derives the types and the statement.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $environment = $derivation->environment();
        foreach ($this->types as $type) {
            $type->deriveClause($derivation, $environment);
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
        if ($this->types !== []) {
            $out->symbol('(')->list($this->types)->symbol(')');
        }
        $out->keyword('AS')->node($this->statement);
    }
}
