<?php

declare(strict_types=1);

namespace Deriver\Model\Builtin;

use Deriver\Model\CallDescription;
use Deriver\Model\CallModel;
use Deriver\Model\ModelDecision;
use Deriver\Model\ModelDescriptor;
use Deriver\Model\Plan\Action;
use Deriver\Model\Plan\Expression;
use Deriver\Model\Plan\SemanticPlan;
use Deriver\Model\Signature\Parameter;
use Deriver\Model\Signature\Signature;
use Deriver\Value\Term;
use Override;

/**
 * Standard functions use ordinary signatures and declarative intrinsic expressions.
 * @visibility root
 */
final class FunctionModel implements CallModel
{
    /**
     * @param string $name Built-in function
     * @param list<Parameter> $parameters Target signature
     */
    public function __construct(public readonly string $name, public readonly array $parameters)
    {
    }

    /**

     * @return ModelDescriptor Standard model identity.

     */
    #[Override]
    public function descriptor(): ModelDescriptor
    {
        return new ModelDescriptor('php.' . $this->name, '1', $this->name, new Signature($this->parameters, allowExtraArguments: false));
    }

    /**
     * Supplies a common evaluator plan.
     * @param CallDescription $call Bound call metadata
     * @return ModelDecision Intrinsic plan
     */
    #[Override]
    public function describe(CallDescription $call): ModelDecision
    {
        $operands = array_map(static fn (Parameter $parameter): Expression => Expression::parameter($parameter->name), $this->parameters);
        if ($this->name === 'strval') {
            return ModelDecision::handled(new SemanticPlan([Action::returns(new Expression('cast', 'string', $operands))]));
        }
        if ($this->name === 'sort') {
            return ModelDecision::handled(new SemanticPlan([new Action('write-parameter', [new Expression('intrinsic', 'sort-values', $operands)], 'array'), Action::returns(Expression::literal(Term::constant(true)))], writes: ['parameter:array']));
        }
        if ($this->name === 'str_replace') {
            $pair = Expression::parameter('@replacement');
            return ModelDecision::handled(new SemanticPlan([
                new Action('write-parameter', [new Expression('intrinsic', 'replace-pair', $operands)], '@replacement'),
                new Action('write-parameter', [new Expression('array-read', operands: [$pair, Expression::literal(Term::constant('count'))])], 'count'),
                Action::returns(new Expression('array-read', operands: [$pair, Expression::literal(Term::constant('result'))])),
            ], writes: ['parameter:count']));
        }
        return ModelDecision::handled(new SemanticPlan([Action::returns(new Expression('intrinsic', $this->name, $operands))], writes: $this->name === 'is_callable' ? ['parameter:callable_name'] : []));
    }
}
