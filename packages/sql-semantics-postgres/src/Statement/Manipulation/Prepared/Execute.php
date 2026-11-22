<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Prepared;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\QueryFact;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Missing\SessionState;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Shape\OpenStar;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Statement;
use SqlSemantics\Platform\PostgreSql\Rules\Manipulation\Placement;

/**
 * EXECUTE: a run of a prepared statement with values for its parameters.
 *
 * Mirrors PostgreSQL's `ExecuteStmt` (name, params). Rule: PG-EXECUTE-001.
 * The values are derived in the empty environment. The prepared statement
 * is session state, so the rows returned are an open shape that depends on
 * it.
 * Source: https://www.postgresql.org/docs/17/sql-execute.html. Status: Implemented.
 *
 * @visibility SqlSemantics
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
