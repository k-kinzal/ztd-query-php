<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Intrinsic;

use Deriver\Model\Intrinsic\IntrinsicDescriptor;
use Deriver\Model\Intrinsic\IntrinsicEvaluation;
use Deriver\Model\Intrinsic\PureIntrinsic;
use Deriver\Project\TargetProfile;
use Deriver\Value\Term;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(IntrinsicEvaluation::class)]
#[UsesClass(\Deriver\Exception\ModelException::class)]
#[UsesClass(IntrinsicDescriptor::class)]
#[UsesClass(TargetProfile::class)]
#[UsesClass(Term::class)]
#[Small]
final class IntrinsicEvaluationTest extends TestCase
{
    public function testEvaluateRejectsAnArityMismatchAsAContractFailure(): void
    {
        $this->expectException(\Deriver\Exception\ModelContractException::class);
        (new IntrinsicEvaluation())->evaluate(new \Tests\Fake\PolicyOperation('compose'), [], new TargetProfile());
    }

    public function testEvaluatePropagatesTheOriginalFailure(): void
    {
        $intrinsic = self::createStub(PureIntrinsic::class);
        $intrinsic->method('descriptor')->willReturn(new IntrinsicDescriptor('test', '1', 'test', 1, [0]));
        $failure = new \Deriver\Exception\ModelException('intrinsic implementation failed');
        $intrinsic->method('evaluate')->willThrowException($failure);
        $this->expectExceptionObject($failure);
        (new IntrinsicEvaluation())->evaluate($intrinsic, [Term::parameter('x')], new TargetProfile());
    }

    public function testEvaluateRejectsIncompleteArguments(): void
    {
        $this->expectException(\Deriver\Exception\ModelContractException::class);
        (new IntrinsicEvaluation())->evaluate(new \Tests\Fake\PolicyOperation('compose'), [Term::parameter('x')], new TargetProfile());
    }

    public function testEvaluateCannotDiscardConfidentialInputMetadata(): void
    {
        $intrinsic = self::createStub(PureIntrinsic::class);
        $intrinsic->method('descriptor')->willReturn(new IntrinsicDescriptor('test', '1', 'test', 1, [0]));
        $intrinsic->method('evaluate')->willReturn(Term::constant('derived private text'));
        $result = (new IntrinsicEvaluation())->evaluate($intrinsic, [Term::constant('private', true)], new TargetProfile());
        self::assertTrue($result->isSecret());
        self::assertSame('derived private text', $result->native());
    }

    public function testEvaluatePreservesAnUnchangedPublicIntrinsicResult(): void
    {
        $intrinsic = self::createStub(PureIntrinsic::class);
        $intrinsic->method('descriptor')->willReturn(new IntrinsicDescriptor('test', '1', 'test', 1, [0]));
        $value = Term::constant('public');
        $intrinsic->method('evaluate')->willReturn($value);
        self::assertSame($value, (new IntrinsicEvaluation())->evaluate($intrinsic, [Term::constant('input')], new TargetProfile()));
    }
}
