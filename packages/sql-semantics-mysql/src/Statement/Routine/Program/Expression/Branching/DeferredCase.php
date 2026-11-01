<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Branching;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Expression\Preparation\DeferredArguments;
use SqlSemantics\Platform\MySql\Statement\Expression\Branching\CaseBranch;
use SqlSemantics\Platform\MySql\Statement\Expression\RowOperand;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Type\Invalid;
use SqlSemantics\Statement\Type\Nullability;

/**
 * A stored CASE expression with an invocation-time operand condition.
 *
 * All branch roles remain explicit. Preparation visits each WHEN/result pair
 * before the compared operand and ELSE, independently of branch selection.
 *
 * @visibility public
 * @example Retaining a deferred compared value with ordinary branches
 *     $row = new \SqlSemantics\Platform\MySql\Statement\Expression\Row([new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('2')]);
 *     $branch = new \SqlSemantics\Platform\MySql\Statement\Expression\Branching\CaseBranch(new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1'), new \SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral());
 *     (new \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Branching\DeferredCase($row, [$branch])) instanceof \SqlSemantics\Statement\Scalar // => false
 */
final class DeferredCase implements DeferredOperand
{
    use \SqlSemantics\Statement\Semantics\ScalarSemantics;
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @var non-empty-list<CaseBranch|DeferredCaseBranch> The complete ordered branches
     */
    public readonly array $branches;

    /**
     * @param Scalar|RowOperand|DeferredOperand|null $operand The compared value, or null for searched CASE
     * @param list<CaseBranch|DeferredCaseBranch> $branches The complete WHEN/result pairs
     * @param Scalar|RowOperand|DeferredOperand|null $else The ELSE result, when written
     */
    public function __construct(public readonly Scalar|RowOperand|DeferredOperand|null $operand, array $branches, public readonly Scalar|RowOperand|DeferredOperand|null $else = null, ?ScalarFact $scalarInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        if ($scalarInformation !== null) {
            $this->initializeScalarInformation($scalarInformation);
        }
        $items = [];
        $deferred = ($operand !== null && !$operand instanceof Scalar) || ($else !== null && !$else instanceof Scalar);
        foreach (Check::listOf($branches, Node::class, 'A CASE expression has at least one WHEN branch.', 1) as $branch) {
            Check::input($branch instanceof CaseBranch || $branch instanceof DeferredCaseBranch, 'CASE branches retain their condition and result roles.');
            $items[] = $branch;
            $deferred = $deferred || $branch instanceof DeferredCaseBranch;
        }
        Check::input($deferred, 'A deferred CASE expression retains a preparation condition.');
        $this->branches = $items;
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Binds every retained dependency without selecting or invoking a branch.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        foreach ($this->operands() as $operand) {
            $derivation->scalar($operand, $environment);
        }
        return new ScalarFact(new Invalid($this->problem()), Nullability::Dependent);
    }

    /**
     * @return non-empty-list<Scalar|RowOperand|DeferredOperand> Dependencies in CASE preparation order
     */
    public function operands(): array
    {
        $items = [];
        foreach ($this->branches as $branch) {
            $items[] = $branch->condition;
            $items[] = $branch->result;
        }
        if ($this->operand !== null) {
            $items[] = $this->operand;
        }
        if ($this->else !== null) {
            $items[] = $this->else;
        }
        return $items;
    }

    /**
     * Preserves the first deferred condition in native preparation order.
     */
    public function problem(): Diagnostic
    {
        return (new DeferredArguments())->problem($this->operands());
    }

    /**
     * Writes the compared operand, every branch and the optional ELSE result.
     */
    public function render(Output $out): void
    {
        $out->keyword('CASE')->node($this->operand);
        foreach ($this->branches as $branch) {
            $out->node($branch);
        }
        if ($this->else !== null) {
            $out->keyword('ELSE')->node($this->else);
        }
        $out->keyword('END');
    }
}
