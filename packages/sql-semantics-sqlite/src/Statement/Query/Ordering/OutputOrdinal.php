<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Query\Ordering;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Rules\Query\Ordinals;
use SqlSemantics\Platform\Sqlite\Statement\Query\Problem\OrdinalOutOfRange;
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
 * An ORDER BY or GROUP BY term that is an integer constant: a request for the result column at that position.
 *
 * This is a different request from the constant it is written as: SQLite
 * does not evaluate the constant but sorts or groups by the result column it
 * counts to.
 *
 * Rule: SQLITE-OUTPUT-ORDINAL-001. The constant is one that
 * SQLITE-ORDINAL-001 recognises. A position between 1 and the number of
 * result columns denotes that result column and has its facts; any other
 * position is out of range. When the result columns are not all known, a
 * position beyond the known ones depends on the missing inputs.
 * Source: https://sqlite.org/lang_select.html#the_order_by_clause. Status: Implemented.
 *
 * @visibility public
 * @example Reading the result column an ordinal denotes
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze("SELECT 'x' AS a, 2 AS b ORDER BY 2");
 *     $query->facts->scalar($query->statement->orderBy[0]->expression)->resolution->field->name->value // => 'b'
 * @example Refusing an expression that is no integer constant
 *     new \SqlSemantics\Platform\Sqlite\Statement\Query\Ordering\OutputOrdinal(new \SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\TextLiteral('1')) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class OutputOrdinal implements Scalar
{
    use Snapshot;

    /**
     * @param Scalar $constant The integer constant as written
     */
    public function __construct(public readonly Scalar $constant)
    {
        Check::input((new Ordinals())->value($constant) !== null, 'A result column position is written as an integer constant.');
    }

    /**
     * Answers the position the constant counts to; the first result column is 1.
     */
    public function position(): int
    {
        return (new Ordinals())->value($this->constant) ?? 0;
    }

    /**
     * Derives the constant and the result column the position denotes among the result columns the environment lists.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $derivation->scalar($this->constant, $environment);
        $position = $this->position();
        foreach ($environment->aliases as $field) {
            if ($field->position === $position - 1) {
                return new ScalarFact($field->type, $field->nullability, new AliasTarget($field));
            }
        }
        $missing = [];
        foreach ($environment->relations as $relation) {
            array_push($missing, ...$relation->shape->missing);
        }
        if ($missing !== [] && $position >= 1) {
            return new ScalarFact(new Dependent($missing), Nullability::Dependent);
        }
        $problem = new OrdinalOutOfRange($position, count($environment->aliases));

        return new ScalarFact(new Invalid($problem), Nullability::Dependent, $problem);
    }

    /**
     * Writes the constant.
     */
    public function render(Output $out): void
    {
        $out->node($this->constant);
    }
}
