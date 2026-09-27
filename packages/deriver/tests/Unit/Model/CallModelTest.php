<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use Deriver\Model\CallDescription;
use Deriver\Model\CallModel;
use Deriver\Model\ModelDescriptor;
use Deriver\Model\Plan\SemanticPlan;
use Deriver\Model\Signature\Signature;
use Deriver\Project\TargetProfile;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Small]
final class CallModelTest extends TestCase
{
    public function testDescriptorPreservesTheSemanticContract(): void
    {
        self::assertContains(CallModel::class, class_implements(\Tests\Fake\PlanModel::class));
    }
    public function testDescribeReturnsDeclarativeSemanticsWithTheSelectedSignature(): void
    {
        $plan = new SemanticPlan([]);
        $model = new \Tests\Fake\PlanModel(new ModelDescriptor('example', '1', 'f'), $plan);
        self::assertSame($plan, $model->describe(new CallDescription('f', new Signature(), new TargetProfile()))->plan);
    }
}
