<?php

declare(strict_types=1);

namespace Tests\Unit\Model;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
#[Small]
final class CallModelTest extends TestCase
{
    public function testDescriptorPreservesTheSemanticContract(): void
    {
        self::assertContains(\Deriver\Model\CallModel::class, class_implements(\Tests\Fake\PlanModel::class));
    }
    public function testDescribeReturnsDeclarativeSemanticsWithTheSelectedSignature(): void
    {
        $plan = new \Deriver\Model\Plan\SemanticPlan([]);
        $model = new \Tests\Fake\PlanModel(new \Deriver\Model\ModelDescriptor('example', '1', 'f'), $plan);
        self::assertSame($plan, $model->describe(new \Deriver\Model\CallDescription('f', new \Deriver\Model\Signature\Signature(), new \Deriver\Api\Project\TargetProfile()))->plan);
    }
}
