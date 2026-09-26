<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Intrinsic;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Model\Intrinsic\PureIntrinsic
 */
#[CoversClass(\Deriver\Model\Intrinsic\PureIntrinsic::class)]
#[UsesClass(\Deriver\Api\Project\TargetProfile::class)]
#[UsesClass(\Deriver\Model\Intrinsic\IntrinsicDescriptor::class)]
#[UsesClass(\Deriver\Value\Term::class)]
#[Small]
final class PureIntrinsicTest extends TestCase
{
    public function testDescriptorDeclaresBothOperandDependencies(): void
    {
        self::assertSame([0, 1], (new \Tests\Fake\PolicyOperation('compose'))->descriptor()->dependencies);
    }
    public function testEvaluateKeepsPartialInputsAsDependencies(): void
    {
        $left = \Deriver\Value\Term::parameter('left');
        $right = \Deriver\Value\Term::parameter('right');
        $value = (new \Tests\Fake\PolicyOperation('compose'))->evaluate([$left, $right], new \Deriver\Api\Project\TargetProfile());
        self::assertSame([$left, $right], $value->operands);
        self::assertSame('policy.compose', $value->literal);
    }
}
