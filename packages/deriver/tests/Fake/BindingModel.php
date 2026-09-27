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
 * Observes public model metadata without inspecting application objects or solver memory.
 * @visibility root
 */
final class BindingModel implements CallModel
{
    /**
     * @return ModelDescriptor Fixed signature for metadata observation
     */
    #[Override]
    public function descriptor(): ModelDescriptor
    {
        return new ModelDescriptor('example.bindings', '1', 'metadata', new Signature([new Parameter('id', default: Term::constant(3)), new Parameter('rest', variadic: true)]));
    }

    /**
     * @param CallDescription $call Immutable normalized metadata
     * @return ModelDecision A plan exposing the supplied metadata for contract assertions
     */
    #[Override]
    public function describe(CallDescription $call): ModelDecision
    {
        $bindings = $call->arguments;
        return ModelDecision::handled(new SemanticPlan([Action::returns(Expression::literal(Term::array([
            'id' => $bindings->arguments['id']->value,
            'supplied' => Term::constant($bindings->arguments['id']->supplied),
            'rest' => $bindings->arguments['rest']->value,
            'evaluated' => Term::constant($bindings->evaluated),
            'version' => Term::constant($call->dependencyVersions['example/library'] ?? ''),
        ])))]));
    }
}
