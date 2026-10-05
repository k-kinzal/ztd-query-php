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
        if ($this->name === 'usort') {
            return ModelDecision::handled(new SemanticPlan([new Action('write-parameter', [new Expression('intrinsic', 'callback-sort', $operands)], 'array'), Action::returns(Expression::literal(Term::constant(true)))], writes: ['parameter:array']));
        }
        if (in_array($this->name, ['array_shift', 'array_pop', 'array_push', 'array_unshift'], true)) {
            return ModelDecision::handled(new SemanticPlan($this->mutation($operands), writes: ['parameter:array']));
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

    /**
     * Writes the array updated by a by-reference array mutation, then returns or throws.
     * @param list<Expression> $operands Bound array and values
     * @return list<Action> Removals branch on emptiness, appends on whether every value fits, and prepends always complete
     */
    public function mutation(array $operands): array
    {
        $record = Expression::parameter('@mutation');
        $part = static fn (string $key): Expression => new Expression('array-read', operands: [$record, Expression::literal(Term::constant($key))]);
        $complete = [new Action('write-parameter', [$part('array')], 'array'), Action::returns($part('result'))];
        if ($this->name === 'array_push') {
            $complete = [Action::choice($part('appended'), $complete, [new Action('write-parameter', [$part('partial')], 'array'), Action::throws(Expression::literal(new Term('throwable', 'Error')))])];
        }
        $actions = [new Action('write-parameter', [new Expression('intrinsic', $this->name, $operands)], '@mutation'), ...$complete];
        if ($this->name === 'array_shift' || $this->name === 'array_pop') {
            return [Action::choice(Expression::parameter('array'), $actions, [Action::returns(Expression::literal(Term::constant(null)))])];
        }
        return $actions;
    }
}
