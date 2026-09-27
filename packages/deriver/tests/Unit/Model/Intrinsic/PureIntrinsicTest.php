<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Intrinsic;

use Deriver\Model\Intrinsic\PureIntrinsic;
use Deriver\Project\TargetProfile;
use Deriver\Value\Term;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Model\Intrinsic\PureIntrinsic
 */
#[CoversClass(PureIntrinsic::class)]
#[UsesClass(\Deriver\Model\Intrinsic\IntrinsicDescriptor::class)]
#[UsesClass(TargetProfile::class)]
#[UsesClass(Term::class)]
#[Small]
final class PureIntrinsicTest extends TestCase
{
    public function testDescriptorDeclaresBothOperandDependencies(): void
    {
        self::assertSame([0, 1], (new \Tests\Fake\PolicyOperation('compose'))->descriptor()->dependencies);
    }
    public function testEvaluateKeepsPartialInputsAsDependencies(): void
    {
        $left = Term::parameter('left');
        $right = Term::parameter('right');
        $value = (new \Tests\Fake\PolicyOperation('compose'))->evaluate([$left, $right], new TargetProfile());
        self::assertSame([$left, $right], $value->operands);
        self::assertSame('policy.compose', $value->literal);
    }
}
