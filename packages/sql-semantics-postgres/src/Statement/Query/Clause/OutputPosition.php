<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Query\Clause;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\ScalarFact;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Reference\Column\AliasTarget;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Dependent;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Invalid;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\PostgreSql\Rules\Query\Positions;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Problem\OrderingClause;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Problem\PositionOutOfRange;

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
 * @visibility SqlSemantics
 * @example Refusing a position that is not an integer constant
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\OutputPosition(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral(), \SqlSemantics\Platform\PostgreSql\Statement\Query\Problem\OrderingClause::OrderBy) // throws \SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction
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
