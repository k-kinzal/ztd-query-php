<?php

declare (strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Intrinsic;

use Deriver\Deriver\Evaluator\Intrinsic\IntrinsicDescriptor;
use Deriver\Deriver\Evaluator\Intrinsic\IntrinsicEvaluation;
use Deriver\Deriver\Evaluator\Intrinsic\PureIntrinsic;
use Deriver\Deriver\Evaluator\TargetProfile;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Evaluator\TermKind;
use Deriver\Exception\EvaluationContractException;
use Deriver\Exception\EvaluationException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(IntrinsicEvaluation::class)]
#[UsesClass(EvaluationException::class)]
#[UsesClass(IntrinsicDescriptor::class)]
#[UsesClass(TargetProfile::class)]
#[UsesClass(Term::class)]
#[Small]
final class IntrinsicEvaluationTest extends TestCase
{
    public function testEvaluateRejectsAnArityMismatchAsAContractFailure(): void
    {
        $this->expectException(EvaluationContractException::class);
        (new IntrinsicEvaluation())->evaluate(new class ('compose') implements PureIntrinsic {
            /**
             * @param string $operation compose, override, or uncertain-tags
             */
            public function __construct(public readonly string $operation)
            {
            }
            /**
             * @return IntrinsicDescriptor Explicit input dependencies
             */
            #[Override]
            public function descriptor(): IntrinsicDescriptor
            {
                return new IntrinsicDescriptor('example.' . $this->operation, '1', 'policy.' . $this->operation, 2, [0, 1]);
            }
            /**
             * @param list<Term> $arguments Policy operands
             * @param TargetProfile $target Declared semantics
             * @return Term Structured policy with independent fields
             */
            #[Override]
            public function evaluate(array $arguments, TargetProfile $target): Term
            {
                [$left, $right] = $arguments;
                if ($left->kind !== TermKind::Array || $right->kind !== TermKind::Array) {
                    return new Term(TermKind::Intrinsic, 'policy.' . $this->operation, $arguments, ['type' => 'array']);
                }
                if ($this->operation === 'override') {
                    return Term::array(array_replace($left->operands, $right->operands));
                }
                $ttl = $left->operands['ttl'] ?? Term::opaque('UNKNOWN_TTL');
                $other = $right->operands['ttl'] ?? $ttl;
                $ttl = is_int($ttl->literal) && is_int($other->literal) ? Term::constant(min($ttl->literal, $other->literal)) : new Term(TermKind::Intrinsic, 'policy.minimum', [$ttl, $other]);
                $tags = $this->operation === 'uncertain-tags' ? Term::opaque('UNKNOWN_TAG_TRANSFORM') : Term::array(array_values([...$left->operands['tags']->operands ?? [], ...$right->operands['tags']->operands ?? []]));
                return Term::array(['ttl' => $ttl, 'tags' => $tags]);
            }
        }, [], new TargetProfile());
    }
    public function testEvaluatePropagatesTheOriginalFailure(): void
    {
        $intrinsic = self::createStub(PureIntrinsic::class);
        $intrinsic->method('descriptor')->willReturn(new IntrinsicDescriptor('test', '1', 'test', 1, [0]));
        $failure = new EvaluationException('intrinsic implementation failed');
        $intrinsic->method('evaluate')->willThrowException($failure);
        $this->expectExceptionObject($failure);
        (new IntrinsicEvaluation())->evaluate($intrinsic, [Term::parameter('x')], new TargetProfile());
    }
    public function testEvaluateRejectsIncompleteArguments(): void
    {
        $this->expectException(EvaluationContractException::class);
        (new IntrinsicEvaluation())->evaluate(new class ('compose') implements PureIntrinsic {
            /**
             * @param string $operation compose, override, or uncertain-tags
             */
            public function __construct(public readonly string $operation)
            {
            }
            /**
             * @return IntrinsicDescriptor Explicit input dependencies
             */
            #[Override]
            public function descriptor(): IntrinsicDescriptor
            {
                return new IntrinsicDescriptor('example.' . $this->operation, '1', 'policy.' . $this->operation, 2, [0, 1]);
            }
            /**
             * @param list<Term> $arguments Policy operands
             * @param TargetProfile $target Declared semantics
             * @return Term Structured policy with independent fields
             */
            #[Override]
            public function evaluate(array $arguments, TargetProfile $target): Term
            {
                [$left, $right] = $arguments;
                if ($left->kind !== TermKind::Array || $right->kind !== TermKind::Array) {
                    return new Term(TermKind::Intrinsic, 'policy.' . $this->operation, $arguments, ['type' => 'array']);
                }
                if ($this->operation === 'override') {
                    return Term::array(array_replace($left->operands, $right->operands));
                }
                $ttl = $left->operands['ttl'] ?? Term::opaque('UNKNOWN_TTL');
                $other = $right->operands['ttl'] ?? $ttl;
                $ttl = is_int($ttl->literal) && is_int($other->literal) ? Term::constant(min($ttl->literal, $other->literal)) : new Term(TermKind::Intrinsic, 'policy.minimum', [$ttl, $other]);
                $tags = $this->operation === 'uncertain-tags' ? Term::opaque('UNKNOWN_TAG_TRANSFORM') : Term::array(array_values([...$left->operands['tags']->operands ?? [], ...$right->operands['tags']->operands ?? []]));
                return Term::array(['ttl' => $ttl, 'tags' => $tags]);
            }
        }, [Term::parameter('x')], new TargetProfile());
    }
    public function testEvaluateReturnsTheIntrinsicResult(): void
    {
        $intrinsic = self::createStub(PureIntrinsic::class);
        $intrinsic->method('descriptor')->willReturn(new IntrinsicDescriptor('test', '1', 'test', 1, [0]));
        $intrinsic->method('evaluate')->willReturn(Term::constant('derived private text'));
        $result = (new IntrinsicEvaluation())->evaluate($intrinsic, [Term::constant('private')], new TargetProfile());
        self::assertSame('derived private text', $result->native());
    }
    public function testEvaluatePreservesTheReturnedTerm(): void
    {
        $intrinsic = self::createStub(PureIntrinsic::class);
        $intrinsic->method('descriptor')->willReturn(new IntrinsicDescriptor('test', '1', 'test', 1, [0]));
        $value = Term::constant('public');
        $intrinsic->method('evaluate')->willReturn($value);
        self::assertSame($value, (new IntrinsicEvaluation())->evaluate($intrinsic, [Term::constant('input')], new TargetProfile()));
    }
}
