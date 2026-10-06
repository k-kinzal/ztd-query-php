<?php

declare(strict_types=1);

namespace Tests\Fake;

use Deriver\Model\CallDescription;
use Deriver\Model\DemandModel;
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
 * Selects a fixture plan using one input while leaving the other input unevaluated.
 * @visibility root
 */
final class SelectiveModel implements DemandModel
{
    /**
     * Declares the two fixture parameters.
     */
    #[Override]
    public function descriptor(): ModelDescriptor
    {
        return new ModelDescriptor('fixture.selective', '1', 'heavy', new Signature([new Parameter('mode'), new Parameter('unused')]));
    }

    /**
     * @return list<string> Input needed only for model selection
     */
    #[Override]
    public function demand(CallDescription $call): array
    {
        return ['mode'];
    }

    /**
     * Chooses a constant plan or explicitly declines source replacement.
     */
    #[Override]
    public function describe(CallDescription $call): ModelDecision
    {
        return $call->arguments->arguments['mode']->value->literal === 'fast' ? ModelDecision::handled(new SemanticPlan([Action::returns(Expression::literal(Term::constant(10)))])) : ModelDecision::declined();
    }
}
