<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Query\Clause;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Query\Positions;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Problem\OrderingClause;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Problem\PositionOutOfRange;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Reference\Column\AliasTarget;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Nullability;

/**
 * An integer constant in ORDER BY, GROUP BY or DISTINCT ON: the output column at that position, counted from one.
 *
 * Rule: PG-OUTPUT-POSITION-002. The term denotes the output field at the
 * position and has its type and NULL fact. The environment the holder
 * derives it in lists the known output fields as aliases, in order up to the
 * first star that cannot be expanded, and holds the relations of that star
 * when there is one. A position past the known fields depends on the missing
 * inputs of such a star; without one it is reported.
 * Source: https://www.postgresql.org/docs/17/sql-select.html#SQL-ORDERBY.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the field an ordering position denotes
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT 1 AS a ORDER BY 1');
 *     $query->facts->scalar($query->statement->options->order[0]->expression)->resolution->field->name->value // => 'a'
 * @example Refusing a position that is not an integer constant
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\OutputPosition(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral(), \SqlSemantics\Platform\PostgreSql\Statement\Query\Problem\OrderingClause::OrderBy) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class OutputPosition implements Scalar
{
    use Snapshot;

    /**
     * @param Scalar $position The integer constant as written
     * @param OrderingClause $clause The clause the position is written in
     */
    public function __construct(public readonly Scalar $position, public readonly OrderingClause $clause)
    {
        Check::input((new Positions())->value($position) !== null, 'An output position is an integer constant.');
    }

    /**
     * Answers the position as an exact decimal integer.
     */
    public function value(): string
    {
        return (string) (new Positions())->value($this->position);
    }

    /**
     * Finds the output field at the position among the aliases of the environment.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $derivation->scalar($this->position, new Environment($derivation->context));
        $value = $this->value();
        $index = strlen($value) <= 9 && !str_starts_with($value, '-') ? (int) $value - 1 : -1;
        $field = $environment->aliases[$index] ?? null;
        if ($index >= 0 && $field !== null) {
            return new ScalarFact($field->type, $field->nullability, new AliasTarget($field));
        }
        $missing = [];
        foreach ($environment->relations as $relation) {
            array_push($missing, ...$relation->shape->missing);
        }
        if ($missing !== [] && $index >= 0) {
            return new ScalarFact(new Dependent($missing), Nullability::Dependent);
        }
        $problem = new PositionOutOfRange($this->clause, $value);
        $derivation->report($problem);

        return new ScalarFact(new Invalid($problem), Nullability::Dependent);
    }

    /**
     * Writes the constant.
     */
    public function render(Output $out): void
    {
        $out->node($this->position);
    }
}
