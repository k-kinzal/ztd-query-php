<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Builtin;

use Deriver\Deriver\Evaluator\Call\CallDecision;
use Deriver\Deriver\Evaluator\Call\CallDescription;
use Deriver\Deriver\Evaluator\Call\CallDescriptor;
use Deriver\Deriver\Evaluator\Call\CallEvaluator;
use Deriver\Deriver\Evaluator\Call\Plan\Action;
use Deriver\Deriver\Evaluator\Call\Plan\Expression;
use Deriver\Deriver\Evaluator\Call\Plan\SemanticPlan;
use Deriver\Deriver\Evaluator\Declaration\Parameter;
use Deriver\Deriver\Evaluator\Declaration\Signature;
use Deriver\Deriver\Evaluator\Term;
use Override;

/**
 * Standard functions use ordinary signatures and declarative intrinsic expressions.
 * @visibility root
 */
final class FunctionEvaluator implements CallEvaluator
{
    /**
     * @param string $name Built-in function
     * @param list<Parameter> $parameters Target signature
     */
    public function __construct(public readonly string $name, public readonly array $parameters)
    {
    }

    /**

     * @return CallDescriptor Standard evaluator identity.

     */
    #[Override]
    public function descriptor(): CallDescriptor
    {
        return new CallDescriptor('php.' . $this->name, '1', $this->name, new Signature($this->parameters, allowExtraArguments: false));
    }

    /**
     * Supplies a common evaluator plan.
     * @param CallDescription $call Bound call metadata
     * @return CallDecision Intrinsic plan
     */
    #[Override]
    public function evaluate(CallDescription $call): CallDecision
    {
        $operands = array_map(static fn (Parameter $parameter): Expression => Expression::parameter($parameter->name), $this->parameters);
        if ($this->name === 'strval') {
            return CallDecision::handled(new SemanticPlan([Action::returns(new Expression(\Deriver\Deriver\Evaluator\Call\Plan\ExpressionKind::Cast, 'string', $operands))]));
        }
        if ($this->name === 'sort') {
            return CallDecision::handled(new SemanticPlan([new Action(\Deriver\Deriver\Evaluator\Call\Plan\ActionKind::WriteParameter, [new Expression(\Deriver\Deriver\Evaluator\Call\Plan\ExpressionKind::Intrinsic, 'sort-values', $operands)], 'array'), Action::returns(Expression::literal(Term::constant(true)))], writes: ['parameter:array']));
        }
        if ($this->name === 'usort') {
            return CallDecision::handled(new SemanticPlan([new Action(\Deriver\Deriver\Evaluator\Call\Plan\ActionKind::WriteParameter, [new Expression(\Deriver\Deriver\Evaluator\Call\Plan\ExpressionKind::Intrinsic, 'callback-sort', $operands)], 'array'), Action::returns(Expression::literal(Term::constant(true)))], writes: ['parameter:array']));
        }
        if (in_array($this->name, ['array_shift', 'array_pop', 'array_push', 'array_unshift'], true)) {
            return CallDecision::handled(new SemanticPlan($this->mutation($operands), writes: ['parameter:array']));
        }
        if ($this->name === 'str_replace') {
            $pair = Expression::parameter('@replacement');
            return CallDecision::handled(new SemanticPlan([
                new Action(\Deriver\Deriver\Evaluator\Call\Plan\ActionKind::WriteParameter, [new Expression(\Deriver\Deriver\Evaluator\Call\Plan\ExpressionKind::Intrinsic, 'replace-pair', $operands)], '@replacement'),
                new Action(\Deriver\Deriver\Evaluator\Call\Plan\ActionKind::WriteParameter, [new Expression(\Deriver\Deriver\Evaluator\Call\Plan\ExpressionKind::ArrayRead, operands: [$pair, Expression::literal(Term::constant('count'))])], 'count'),
                Action::returns(new Expression(\Deriver\Deriver\Evaluator\Call\Plan\ExpressionKind::ArrayRead, operands: [$pair, Expression::literal(Term::constant('result'))])),
            ], writes: ['parameter:count']));
        }
        return CallDecision::handled(new SemanticPlan([Action::returns(new Expression(\Deriver\Deriver\Evaluator\Call\Plan\ExpressionKind::Intrinsic, $this->name, $operands))], writes: $this->name === 'is_callable' ? ['parameter:callable_name'] : []));
    }

    /**
     * Writes the array updated by a by-reference array mutation, then returns or throws.
     * @param list<Expression> $operands Bound array and values
     * @return list<Action> Removals branch on emptiness, appends on whether every value fits, and prepends always complete
     */
    public function mutation(array $operands): array
    {
        $record = Expression::parameter('@mutation');
        $part = static fn (string $key): Expression => new Expression(\Deriver\Deriver\Evaluator\Call\Plan\ExpressionKind::ArrayRead, operands: [$record, Expression::literal(Term::constant($key))]);
        $complete = [new Action(\Deriver\Deriver\Evaluator\Call\Plan\ActionKind::WriteParameter, [$part('array')], 'array'), Action::returns($part('result'))];
        if ($this->name === 'array_push') {
            $complete = [Action::choice($part('appended'), $complete, [new Action(\Deriver\Deriver\Evaluator\Call\Plan\ActionKind::WriteParameter, [$part('partial')], 'array'), Action::throws(Expression::literal(new Term(\Deriver\Deriver\Evaluator\TermKind::Throwable, 'Error')))])];
        }
        $actions = [new Action(\Deriver\Deriver\Evaluator\Call\Plan\ActionKind::WriteParameter, [new Expression(\Deriver\Deriver\Evaluator\Call\Plan\ExpressionKind::Intrinsic, $this->name, $operands)], '@mutation'), ...$complete];
        if ($this->name === 'array_shift' || $this->name === 'array_pop') {
            return [Action::choice(Expression::parameter('array'), $actions, [Action::returns(Expression::literal(Term::constant(null)))])];
        }
        return $actions;
    }
}
