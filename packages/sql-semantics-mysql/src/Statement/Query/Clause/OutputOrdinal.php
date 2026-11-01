<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Query\Clause;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Query\SortScopes;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Unary;
use SqlSemantics\Platform\MySql\Statement\Literal\BooleanLiteral;
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\OrdinalOutOfRange;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Operand;
use SqlSemantics\Statement\Reference\Column\AliasTarget;
use SqlSemantics\Statement\Type\Dependent;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Nullability;

/**
 * A position in the select list written as an integer in ORDER BY or GROUP BY.
 *
 * Rule: MYSQL-ORDINAL-001. An unsigned integer literal written as an ORDER
 * BY or GROUP BY item denotes the select list item at that position,
 * counting from one. Parentheses and unary plus preserve this role. In MySQL
 * 5.6 and 5.7, boolean literals also denote positions zero and one; their
 * ordinal representation has a legacy-only release domain. The query derives the ordinal in an environment whose
 * aliases are the output fields in order; when the select list holds a star
 * whose columns are not all known, the environment holds the relations of
 * that star and the position depends on their declarations. A position
 * outside the select list is reported. Source:
 * https://dev.mysql.com/doc/refman/8.4/en/select.html ("Columns selected for
 * output can be referred to in ORDER BY and GROUP BY clauses using ... column
 * positions"). Status: Implemented.
 *
 * @visibility public
 * @example Reading the position of an ordering item
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT a, b FROM t ORDER BY 2');
 *     $query->query->orderBy[0]->expression->position() // => 2
 * @example Refusing a decimal as a position
 *     new \SqlSemantics\Platform\MySql\Statement\Query\Clause\OutputOrdinal(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1.0')) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class OutputOrdinal implements Operand
{
    use \SqlSemantics\Statement\Semantics\ScalarSemantics;
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param NumberLiteral|BooleanLiteral|Grouped|Unary $expression The integer and its positional wrappers
     */
    public function __construct(public readonly NumberLiteral|BooleanLiteral|Grouped|Unary $expression, ?ScalarFact $scalarInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        if ($scalarInformation !== null) {
            $this->initializeScalarInformation($scalarInformation);
        }
        $this->literal();
    }

    /**
     * Answers the integer reached through the positional wrappers.
     */
    public function literal(): NumberLiteral|BooleanLiteral
    {
        $literal = (new SortScopes())->position($this->expression, true);
        Check::input($literal !== null, 'A select list position is an unsigned integer.');
        return $literal;
    }

    /**
     * Answers the position, counting from one; a position beyond the integer range of PHP answers the largest integer.
     */
    public function position(): int
    {
        $literal = $this->literal();
        if ($literal instanceof BooleanLiteral) {
            return $literal->value ? 1 : 0;
        }
        $digits = ltrim($literal->text, '0');

        return strlen($digits) > 18 ? PHP_INT_MAX : (int) $digits;
    }

    /**
     * Answers the written integer or legacy boolean name used by diagnostics.
     */
    public function name(): string
    {
        $literal = $this->literal();
        return $literal instanceof BooleanLiteral ? ($literal->value ? 'TRUE' : 'FALSE') : $literal->text;
    }

    /**
     * Derives the integer and takes the facts of the output field at the position.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $derivation->scalar($this->expression, $environment);
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
        $problem = new OrdinalOutOfRange($this->position(), count($environment->aliases), $this);
        $derivation->report($problem);

        return new ScalarFact(new Invalid($problem), Nullability::Dependent);
    }

    /**
     * Writes the integer.
     */
    public function render(Output $out): void
    {
        $out->node($this->expression);
    }
}
