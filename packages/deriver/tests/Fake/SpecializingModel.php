<?php

declare(strict_types=1);

namespace Tests\Fake;

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
 * Supplies equivalent generic and constant-specialized plans with different graph shapes.
 * @visibility root
 */
final class SpecializingModel implements CallModel
{
    /**
     * @return ModelDescriptor Stable value-passing signature
     */
    #[Override]
    public function descriptor(): ModelDescriptor
    {
        return new ModelDescriptor('example.specialized', '1', 'specialized', new Signature([new Parameter('value', 'int')]));
    }

    /**
     * @param CallDescription $call Immutable abstract inputs
     * @return ModelDecision Equivalent increment semantics for concrete and symbolic inputs
     */
    #[Override]
    public function describe(CallDescription $call): ModelDecision
    {
        $value = $call->arguments->arguments['value']->value;
        if ($value->kind === 'constant' && $value->literal === 1) {
            return ModelDecision::handled(new SemanticPlan([Action::returns(Expression::literal(Term::constant(2)))]));
        }
        return ModelDecision::handled(new SemanticPlan([Action::returns(Expression::binary('+', Expression::parameter('value'), Expression::literal(Term::constant(1))))]));
    }
}
