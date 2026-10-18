<?php

declare (strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Intrinsic;

use Deriver\Deriver\Evaluator\Intrinsic\IntrinsicDescriptor;
use Deriver\Deriver\Evaluator\Intrinsic\PureIntrinsic;
use Deriver\Deriver\Evaluator\TargetProfile;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Evaluator\TermKind;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Deriver\Evaluator\Intrinsic\PureIntrinsic
 */
#[CoversClass(PureIntrinsic::class)]
#[UsesClass(IntrinsicDescriptor::class)]
#[UsesClass(TargetProfile::class)]
#[UsesClass(Term::class)]
#[Small]
final class PureIntrinsicTest extends TestCase
{
    public function testDescriptorDeclaresBothOperandDependencies(): void
    {
        self::assertSame([0, 1], (new class ('compose') implements PureIntrinsic {
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
        })->descriptor()->dependencies);
    }
    public function testEvaluateKeepsPartialInputsAsDependencies(): void
    {
        $left = Term::parameter('left');
        $right = Term::parameter('right');
        $value = (new class ('compose') implements PureIntrinsic {
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
        })->evaluate([$left, $right], new TargetProfile());
        self::assertSame([$left, $right], $value->operands);
        self::assertSame('policy.compose', $value->literal);
    }
}
