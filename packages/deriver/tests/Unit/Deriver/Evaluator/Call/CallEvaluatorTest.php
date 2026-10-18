<?php

declare (strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Call;

use Deriver\Deriver\Evaluator\Builtin\FunctionEvaluator;
use Deriver\Deriver\Evaluator\Call\CallDecision;
use Deriver\Deriver\Evaluator\Call\CallDescription;
use Deriver\Deriver\Evaluator\Call\CallDescriptor;
use Deriver\Deriver\Evaluator\Call\CallEvaluator;
use Deriver\Deriver\Evaluator\Call\Plan\SemanticPlan;
use Deriver\Deriver\Evaluator\Declaration\Signature;
use Deriver\Deriver\Evaluator\TargetProfile;
use Override;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Small]
final class CallEvaluatorTest extends TestCase
{
    public function testDescriptorPreservesTheSemanticContract(): void
    {
        self::assertContains(CallEvaluator::class, class_implements(FunctionEvaluator::class));
    }
    public function testEvaluateReturnsDeclarativeSemanticsWithTheSelectedSignature(): void
    {
        $plan = new SemanticPlan([]);
        $evaluator = new class (new CallDescriptor('example', '1', 'f'), $plan) implements CallEvaluator {
            /**
             * @param CallDescriptor $metadata Stable evaluator registration
             * @param SemanticPlan $plan Ordered API semantics
             */
            public function __construct(public readonly CallDescriptor $metadata, public readonly SemanticPlan $plan)
            {
            }
            /**
             * @return CallDescriptor Stable registration
             */
            #[Override]
            public function descriptor(): CallDescriptor
            {
                return $this->metadata;
            }
            /**
             * @param CallDescription $call Selected target
             * @return CallDecision Declarative fixture semantics
             */
            #[Override]
            public function evaluate(CallDescription $call): CallDecision
            {
                return CallDecision::handled($this->plan);
            }
        };
        self::assertSame($plan, $evaluator->evaluate(new CallDescription('f', new Signature(), new TargetProfile()))->plan);
    }
}
