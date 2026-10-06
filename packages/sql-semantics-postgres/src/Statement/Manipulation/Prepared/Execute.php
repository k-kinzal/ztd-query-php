<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Prepared;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\PostgreSql\Rules\Manipulation\Placement;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Fact\QueryFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Reference\Missing\SessionState;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Shape\OpenStar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * EXECUTE: a run of a prepared statement with values for its parameters.
 *
 * Mirrors PostgreSQL's `ExecuteStmt` (name, params). Rule: PG-EXECUTE-001.
 * The values are derived in the empty environment. The prepared statement
 * is session state, so the rows returned are an open shape that depends on
 * it.
 * Source: https://www.postgresql.org/docs/17/sql-execute.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading a run of a prepared statement
 *     $execute = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze("EXECUTE p (1, 'x')");
 *     [count($execute->statement->parameters), $execute->shape()->complete()] // => [2, false]
 */
final class Execute implements Statement
{
    use Snapshot;

    /**
     * @var list<Scalar> The parameter values in order
     */
    public readonly array $parameters;

    /**
     * @param Name $name The name of the prepared statement
     * @param list<Scalar> $parameters The parameter values in order
     *
     * @throws InvalidConstruction When a value is not an expression
     */
    public function __construct(public readonly Name $name, array $parameters = [])
    {
        $this->parameters = Check::listOf($parameters, Scalar::class, 'The parameters of EXECUTE are expressions.');
    }

    /**
     * Derives the values and records the open rows of the prepared statement.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $this->deriveParameters($derivation);
        $derivation->output($this->rows($derivation));
    }

    /**
     * Derives the values, for a statement that runs the prepared statement for another purpose, such as CREATE TABLE AS EXECUTE.
     */
    public function deriveParameters(Derivation $derivation): void
    {
        $environment = $derivation->environment();
        foreach ($this->parameters as $parameter) {
            $derivation->scalar($parameter, $environment);
        }
        $placement = new Placement();
        $placement->values($this, [], [], $derivation);
        $placement->into($this, $derivation);
    }

    /**
     * Answers the rows of the prepared statement: an open shape that depends on the session.
     */
    public function rows(Derivation $derivation): QueryFact
    {
        return new QueryFact([new OpenStar([new SessionState('the prepared statement ' . $this->name->value)])], $derivation->context->columnNames);
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->keyword('EXECUTE')->name($this->name, NameUse::Column);
        if ($this->parameters !== []) {
            $out->symbol('(')->list($this->parameters)->symbol(')');
        }
    }
}
