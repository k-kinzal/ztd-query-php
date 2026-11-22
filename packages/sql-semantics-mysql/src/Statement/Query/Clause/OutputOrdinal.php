<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Query\Clause;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Resolution\Environment;
use SqlSemantics\Platform\MySql\Model\Statement\Fact\ScalarFact;
use SqlSemantics\Platform\MySql\Model\Statement\Reference\Column\AliasTarget;
use SqlSemantics\Platform\MySql\Model\Statement\Scalar;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Dependent;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Invalid;
use SqlSemantics\Platform\MySql\Model\Statement\Type\Nullability;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberForm;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\OrdinalOutOfRange;

/**
 * A position in the select list written as an integer in ORDER BY or GROUP BY.
 *
 * Rule: MYSQL-ORDINAL-001. An unsigned integer literal written as an ORDER
 * BY or GROUP BY item denotes the select list item at that position,
 * counting from one. The query derives the ordinal in an environment whose
 * aliases are the output fields in order; when the select list holds a star
 * whose columns are not all known, the environment holds the relations of
 * that star and the position depends on their declarations. A position
 * outside the select list is reported. Source:
 * https://dev.mysql.com/doc/refman/8.4/en/select.html ("Columns selected for
 * output can be referred to in ORDER BY and GROUP BY clauses using ... column
 * positions"). Status: Implemented.
 *
 * @visibility SqlSemantics
 * @example Refusing a decimal as a position
 *     new \SqlSemantics\Platform\MySql\Statement\Query\Clause\OutputOrdinal(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1.0')) // throws \SqlSemantics\Platform\MySql\Model\Diagnostic\InvalidConstruction
 */
final class OutputOrdinal implements Scalar
{
    use Snapshot;

    /**
     * @param NumberLiteral $literal The integer as written
     */
    public function __construct(public readonly NumberLiteral $literal)
    {
        Check::input($literal->form === NumberForm::Integer, 'A select list position is an unsigned integer.');
    }

    /**
     * Answers the position, counting from one; a position beyond the integer range of PHP answers the largest integer.
     */
    public function position(): int
    {
        $digits = ltrim($this->literal->text, '0');

        return strlen($digits) > 18 ? PHP_INT_MAX : (int) $digits;
    }

    /**
     * Derives the integer and takes the facts of the output field at the position.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $derivation->scalar($this->literal, $environment);
        $field = $environment->aliases[$this->position() - 1] ?? null;
        if ($field !== null) {
            return new ScalarFact($field->type, $field->nullability, new AliasTarget($field));
        }
        $missing = [];
        foreach ($environment->relations as $relation) {
            array_push($missing, ...$relation->shape->missing);
        }
        if ($missing !== []) {
            return new ScalarFact(new Dependent($missing), Nullability::Dependent);
        }
        $problem = new OrdinalOutOfRange($this->position(), count($environment->aliases));
        $derivation->report($problem);

        return new ScalarFact(new Invalid($problem), Nullability::Dependent);
    }

    /**
     * Writes the integer.
     */
    public function render(Output $out): void
    {
        $out->node($this->literal);
    }
}
