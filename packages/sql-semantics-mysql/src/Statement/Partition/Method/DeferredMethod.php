<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Partition\Method;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Expression\Precedence;
use SqlSemantics\Platform\MySql\Statement\Expression\RowOperand;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;

/**
 * A partition method retaining a non-scalar or subquery operand for execution.
 *
 * The complete operand and method identity survive preparation and stored
 * invocation. Session-dependent operands remain explicit. Release-specific
 * contextual restrictions precede target lookup; the remaining function
 * restriction belongs to invocation.
 * Observed through SQL on MySQL 8.4.7.
 *
 * @visibility public
 * @example Retaining a row operand for a stored HASH alteration
 *     $row = new \SqlSemantics\Platform\MySql\Statement\Expression\Row([new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('2')]);
 *     $method = new \SqlSemantics\Platform\MySql\Statement\Partition\Method\DeferredMethod($row);
 *     $method->expression === $row // => true
 * @example Keeping LINEAR exclusive to HASH methods
 *     $row = new \SqlSemantics\Platform\MySql\Statement\Expression\Row([new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('2')]);
 *     new \SqlSemantics\Platform\MySql\Statement\Partition\Method\DeferredMethod($row, \SqlSemantics\Platform\MySql\Statement\Partition\Method\PartitionKind::Range, true) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Retaining deferred methods in ALTER requests
 *     $row = new \SqlSemantics\Platform\MySql\Statement\Expression\Row([new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('2')]);
 *     $clause = new \SqlSemantics\Platform\MySql\Statement\Partition\PartitionClause(new \SqlSemantics\Platform\MySql\Statement\Partition\Method\DeferredMethod($row), null, null, []);
 *     $alter = new \SqlSemantics\Platform\MySql\Statement\Alter\AlterTable(new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('t')), [new \SqlSemantics\Platform\MySql\Statement\Alter\Partition\PartitionBy($clause)]);
 *     $alter->hasExecutionCondition() // => true
 * @example Retaining intrinsic restrictions alongside a row operand
 *     $row = new \SqlSemantics\Platform\MySql\Statement\Expression\Row([new \SqlSemantics\Platform\MySql\Statement\Call\KeywordCall(\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::User, []), new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1')]);
 *     (new \SqlSemantics\Platform\MySql\Statement\Partition\Method\DeferredMethod($row))->expression === $row // => true
 */
final class DeferredMethod implements PartitionMethod
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @param \SqlSemantics\Statement\Scalar|RowOperand|DeferredOperand $expression The complete non-scalar or subquery partition operand
     * @param PartitionKind|null $kind RANGE or LIST; null denotes HASH
     * @param bool $linear Whether HASH is LINEAR
     */
    public function __construct(public readonly \SqlSemantics\Statement\Scalar|RowOperand|DeferredOperand $expression, public readonly ?PartitionKind $kind = null, public readonly bool $linear = false, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        Check::input(!$expression instanceof \SqlSemantics\Statement\Scalar || (new \SqlSemantics\Platform\MySql\Rules\TableDefinition\PartitionExpressions())->condition($expression, \SqlSemantics\Contract\GrammarRelease::MySql847) === \SqlSemantics\Platform\MySql\Statement\Partition\Problem\OperandCondition::Subquery, 'A scalar retained partition method contains a subquery.');
        Check::input(!$linear || $kind === null, 'Only HASH partitioning accepts LINEAR.');
        Check::input((new Precedence())->admits($expression, Precedence::BIT_EXPR), 'A partition expression needs a grouping to keep its place.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Derives dependencies without giving the refused operand a scalar interpretation.
     */
    public function deriveMethod(Derivation $derivation, Environment $scope): void
    {
        (new \SqlSemantics\Platform\MySql\Rules\TableDefinition\PartitionExpressions())->derive($this->expression, $derivation, $scope);
    }

    /**
     * Writes the retained method and its original operand structure.
     */
    public function render(Output $out): void
    {
        if ($this->linear) {
            $out->keyword('LINEAR');
        }
        $out->keyword($this->kind === null ? 'HASH' : $this->kind->value)->symbol('(')->node($this->expression)->symbol(')');
    }
}
