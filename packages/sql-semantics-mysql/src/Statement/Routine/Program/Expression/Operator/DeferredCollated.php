<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Operator;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Expression\Conversion\CollationLookup;
use SqlSemantics\Platform\MySql\Rules\Expression\Precedence;
use SqlSemantics\Platform\MySql\Rules\Expression\Preparation\DeferredArguments;
use SqlSemantics\Platform\MySql\Statement\Expression\RowOperand;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Type\Invalid;

/**
 * A stored COLLATE whose meaning is bound when the program runs.
 *
 * The operand may require invocation-time preparation, or the collation name
 * may be one MySQL 5.6 and 5.7 resolve only when the statement runs. The name
 * and the complete operand are retained without admitting a non-scalar value
 * into Scalar. Name lookup keeps its release-specific phase.
 *
 * @visibility public
 * @example Preserving a row and its explicit collation
 *     $row = new \SqlSemantics\Platform\MySql\Statement\Expression\Row([new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('2')]);
 *     (new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Operator\DeferredCollated($row, new \SqlSemantics\Statement\Identifier\Name('binary'))) instanceof \SqlSemantics\Statement\Scalar // => false
 */
final class DeferredCollated implements DeferredOperand
{
    use \SqlSemantics\Statement\Semantics\ScalarSemantics;
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param Scalar|RowOperand|DeferredOperand $operand The retained operand
     * @param Name $collation The explicit collation name
     */
    public function __construct(public readonly Scalar|RowOperand|DeferredOperand $operand, public readonly Name $collation, ?ScalarFact $scalarInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        if ($scalarInformation !== null) {
            $this->initializeScalarInformation($scalarInformation);
        }
        Check::input((new Precedence())->fits($operand, Precedence::COLLATION, Precedence::SIMPLE_EXPR), 'The operand of COLLATE needs a grouping to keep its place.');
        Check::input(!$operand instanceof Scalar || \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::named($collation->value) === null, 'A stored COLLATE of a plain scalar names a collation the server resolves only when the statement runs.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Binds the retained operand and the definition-time collation lookup, if any.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $fact = (new CollationLookup())->derive($this, $derivation, $environment);
        return new ScalarFact(new Invalid($this->problem()), $fact->nullability);
    }

    /**
     * @return non-empty-list<Scalar|RowOperand|DeferredOperand> The retained operand
     */
    public function operands(): array
    {
        return [$this->operand];
    }

    /**
     * Preserves the operand's invocation condition.
     */
    public function problem(): Diagnostic
    {
        if ($this->operand instanceof Scalar) {
            return new \SqlSemantics\Platform\MySql\Statement\Expression\Problem\UnknownCollation($this->collation->value, $this);
        }

        return (new DeferredArguments())->problem($this->operands());
    }

    /**
     * Writes the operand and its collation in their semantic roles.
     */
    public function render(Output $out): void
    {
        $out->node($this->operand)->keyword('COLLATE')->name($this->collation, \SqlSemantics\Contract\NameUse::Label);
    }
}
