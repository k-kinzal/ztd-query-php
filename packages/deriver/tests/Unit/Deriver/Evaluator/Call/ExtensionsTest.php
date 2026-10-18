<?php

declare (strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Call;

use Deriver\Deriver\DeriverConfig;
use Deriver\Deriver\Evaluator\Call\Extensions;
use Deriver\Deriver\Evaluator\Call\Registrations;
use Deriver\Deriver\Evaluator\Domain\AbstractDomain;
use Deriver\Deriver\Evaluator\Domain\DomainFact;
use Deriver\Deriver\Evaluator\Domain\DomainLaws;
use Deriver\Deriver\Evaluator\Domain\DomainOperations;
use Deriver\Deriver\Evaluator\Intrinsic\IntrinsicDescriptor;
use Deriver\Deriver\Evaluator\Intrinsic\PureIntrinsic;
use Deriver\Deriver\Evaluator\Projection;
use Deriver\Deriver\Evaluator\TargetProfile;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Evaluator\TermKind;
use Deriver\Exception\InvalidInputException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Deriver\Evaluator\Call\Extensions
 */
#[CoversClass(Extensions::class)]
#[UsesClass(DomainLaws::class)]
#[UsesClass(DomainFact::class)]
#[UsesClass(DomainOperations::class)]
#[UsesClass(IntrinsicDescriptor::class)]
#[UsesClass(DeriverConfig::class)]
#[UsesClass(TargetProfile::class)]
#[UsesClass(Term::class)]
#[Small]
final class ExtensionsTest extends TestCase
{
    public function testRegistersVersionedOperationsAndDomains(): void
    {
        $extensions = new Extensions(new Registrations(intrinsics: [new class ('compose') implements PureIntrinsic {
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
        }], domains: [new class () implements AbstractDomain {
            /**
             * @return string Domain identity
             */
            #[Override]
            public function id(): string
            {
                return 'example.policy';
            }
            /**
             * @return string Semantic version
             */
            #[Override]
            public function version(): string
            {
                return '1';
            }
            /**
             * @return DomainFact Empty set
             */
            #[Override]
            public function bottom(): DomainFact
            {
                return new DomainFact($this->id(), new Term(TermKind::Bottom));
            }
            /**
             * @return DomainFact All policy records
             */
            #[Override]
            public function top(): DomainFact
            {
                return new DomainFact($this->id(), new Term(TermKind::Top));
            }
            /**
             * @param DomainFact $a Contained fact
             * @param DomainFact $b Containing fact
             * @return bool Flat-lattice inclusion
             */
            #[Override]
            public function lessOrEqual(DomainFact $a, DomainFact $b): bool
            {
                return $a->representation->kind === TermKind::Bottom || $b->representation->kind === TermKind::Top || $a->representation->isConcrete() && $b->representation->isConcrete() && $a->representation->native() === $b->representation->native();
            }
            /**
             * @param DomainFact $a First possibility
             * @param DomainFact $b Second possibility
             * @return DomainFact Inclusive alternative
             */
            #[Override]
            public function join(DomainFact $a, DomainFact $b): DomainFact
            {
                if ($this->lessOrEqual($a, $b)) {
                    return $b;
                }
                return $this->lessOrEqual($b, $a) ? $a : $this->top();
            }
            /**
             * @param DomainFact $previous Previous approximation
             * @param DomainFact $next New approximation
             * @return DomainFact Finite widening
             */
            #[Override]
            public function widen(DomainFact $previous, DomainFact $next): DomainFact
            {
                return $this->join($previous, $next);
            }
            /**
             * @param DomainFact $value Policy record
             * @param Projection $projection Selected fields
             * @return DomainFact Projected fact
             */
            #[Override]
            public function project(DomainFact $value, Projection $projection): DomainFact
            {
                $term = $value->representation;
                foreach ($projection->path as $key) {
                    $term = $term->operands[$key] ?? new Term(TermKind::Top);
                }
                return new DomainFact($this->id(), $term);
            }
        }]));
        self::assertArrayHasKey('policy.compose', $extensions->intrinsics);
        self::assertArrayHasKey('example.policy', $extensions->domains);
        self::assertSame('1', $extensions->manifest['domain:example.policy']);
    }
    public function testRejectsDuplicateOperations(): void
    {
        $this->expectException(InvalidInputException::class);
        new Extensions(new Registrations(intrinsics: [new class ('compose') implements PureIntrinsic {
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
        }, new class ('compose') implements PureIntrinsic {
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
        }]));
    }
    #[DataProvider('providerIntrinsicIdentityPairs')]
    public function testRegistersDistinctIntrinsicContractsWithoutDelimiterCollisions(IntrinsicDescriptor $firstDescriptor, IntrinsicDescriptor $secondDescriptor): void
    {
        $first = self::createStub(PureIntrinsic::class);
        $first->method('descriptor')->willReturn($firstDescriptor);
        $second = self::createStub(PureIntrinsic::class);
        $second->method('descriptor')->willReturn($secondDescriptor);
        $a = new Extensions(new Registrations(intrinsics: [$first]));
        $b = new Extensions(new Registrations(intrinsics: [$second]));
        self::assertNotSame($a->manifest, $b->manifest);
    }
    /**
     * @return iterable<string,array{IntrinsicDescriptor,IntrinsicDescriptor}>
     */
    public static function providerIntrinsicIdentityPairs(): iterable
    {
        $base = new IntrinsicDescriptor('example', '1', 'apply', 2, [0]);
        yield 'separator' => [new IntrinsicDescriptor('example', '1:example', 'apply', 2, [0]), new IntrinsicDescriptor('example', '1', 'example:apply', 2, [0])];
        yield 'id' => [$base, new IntrinsicDescriptor('other', '1', 'apply', 2, [0])];
        yield 'version' => [$base, new IntrinsicDescriptor('example', '2', 'apply', 2, [0])];
        yield 'operation' => [$base, new IntrinsicDescriptor('example', '1', 'other', 2, [0])];
        yield 'arity' => [$base, new IntrinsicDescriptor('example', '1', 'apply', 3, [0])];
        yield 'dependencies' => [$base, new IntrinsicDescriptor('example', '1', 'apply', 2, [1])];
        yield 'all dependencies' => [$base, new IntrinsicDescriptor('example', '1', 'apply', 2, [0, 1])];
    }
    #[DataProvider('providerInvalidIntrinsic')]
    public function testRejectsInvalidIntrinsicRegistrationBeforeAdmission(IntrinsicDescriptor $descriptor, string $message): void
    {
        $intrinsic = self::createStub(PureIntrinsic::class);
        $intrinsic->method('descriptor')->willReturn($descriptor);
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage($message);
        new Extensions(new Registrations(intrinsics: [$intrinsic]));
    }
    /**
     * @return iterable<string,array{IntrinsicDescriptor,string}>
     */
    public static function providerInvalidIntrinsic(): iterable
    {
        yield 'empty id' => [new IntrinsicDescriptor('', '1', 'apply', 1, [0]), 'EVALUATOR_CONFLICT'];
        yield 'empty version' => [new IntrinsicDescriptor('example', '', 'apply', 1, [0]), 'EVALUATOR_CONFLICT'];
        yield 'empty operation' => [new IntrinsicDescriptor('example', '1', '', 1, [0]), 'EVALUATOR_CONFLICT'];
        yield 'negative arity' => [new IntrinsicDescriptor('example', '1', 'apply', -1, []), 'EVALUATOR_CONFLICT'];
        yield 'negative dependency' => [new IntrinsicDescriptor('example', '1', 'apply', 1, [-1]), 'EVALUATOR_CONTRACT_VIOLATION'];
        yield 'dependency at arity' => [new IntrinsicDescriptor('example', '1', 'apply', 1, [1]), 'EVALUATOR_CONTRACT_VIOLATION'];
    }
    public function testRejectsARepeatedIdEvenWhenOperationsDiffer(): void
    {
        $first = self::createStub(PureIntrinsic::class);
        $first->method('descriptor')->willReturn(new IntrinsicDescriptor('example', '1', 'first', 0, []));
        $second = self::createStub(PureIntrinsic::class);
        $second->method('descriptor')->willReturn(new IntrinsicDescriptor('example', '1', 'second', 0, []));
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('repeated intrinsic operation or ID');
        new Extensions(new Registrations(intrinsics: [$first, $second]));
    }
    public function testRegistersZeroArityAndTheLastValidDependency(): void
    {
        $first = self::createStub(PureIntrinsic::class);
        $first->method('descriptor')->willReturn(new IntrinsicDescriptor('a', '1', 'first', 0, []));
        $second = self::createStub(PureIntrinsic::class);
        $second->method('descriptor')->willReturn(new IntrinsicDescriptor('z', '1', 'second', 2, [1]));
        $extensions = new Extensions(new Registrations(intrinsics: [$second, $first]));
        self::assertSame(['intrinsic:a', 'intrinsic:z'], array_keys($extensions->manifest));
        self::assertSame(['second' => $second, 'first' => $first], $extensions->intrinsics);
        self::assertSame([], $extensions->domains);
    }
    public function testRejectsARepeatedDomainIdentity(): void
    {
        $this->expectException(InvalidInputException::class);
        $this->expectExceptionMessage('invalid or repeated domain identity');
        new Extensions(new Registrations(domains: [new class () implements AbstractDomain {
            /**
             * @return string Domain identity
             */
            #[Override]
            public function id(): string
            {
                return 'example.policy';
            }
            /**
             * @return string Semantic version
             */
            #[Override]
            public function version(): string
            {
                return '1';
            }
            /**
             * @return DomainFact Empty set
             */
            #[Override]
            public function bottom(): DomainFact
            {
                return new DomainFact($this->id(), new Term(TermKind::Bottom));
            }
            /**
             * @return DomainFact All policy records
             */
            #[Override]
            public function top(): DomainFact
            {
                return new DomainFact($this->id(), new Term(TermKind::Top));
            }
            /**
             * @param DomainFact $a Contained fact
             * @param DomainFact $b Containing fact
             * @return bool Flat-lattice inclusion
             */
            #[Override]
            public function lessOrEqual(DomainFact $a, DomainFact $b): bool
            {
                return $a->representation->kind === TermKind::Bottom || $b->representation->kind === TermKind::Top || $a->representation->isConcrete() && $b->representation->isConcrete() && $a->representation->native() === $b->representation->native();
            }
            /**
             * @param DomainFact $a First possibility
             * @param DomainFact $b Second possibility
             * @return DomainFact Inclusive alternative
             */
            #[Override]
            public function join(DomainFact $a, DomainFact $b): DomainFact
            {
                if ($this->lessOrEqual($a, $b)) {
                    return $b;
                }
                return $this->lessOrEqual($b, $a) ? $a : $this->top();
            }
            /**
             * @param DomainFact $previous Previous approximation
             * @param DomainFact $next New approximation
             * @return DomainFact Finite widening
             */
            #[Override]
            public function widen(DomainFact $previous, DomainFact $next): DomainFact
            {
                return $this->join($previous, $next);
            }
            /**
             * @param DomainFact $value Policy record
             * @param Projection $projection Selected fields
             * @return DomainFact Projected fact
             */
            #[Override]
            public function project(DomainFact $value, Projection $projection): DomainFact
            {
                $term = $value->representation;
                foreach ($projection->path as $key) {
                    $term = $term->operands[$key] ?? new Term(TermKind::Top);
                }
                return new DomainFact($this->id(), $term);
            }
        }, new class () implements AbstractDomain {
            /**
             * @return string Domain identity
             */
            #[Override]
            public function id(): string
            {
                return 'example.policy';
            }
            /**
             * @return string Semantic version
             */
            #[Override]
            public function version(): string
            {
                return '1';
            }
            /**
             * @return DomainFact Empty set
             */
            #[Override]
            public function bottom(): DomainFact
            {
                return new DomainFact($this->id(), new Term(TermKind::Bottom));
            }
            /**
             * @return DomainFact All policy records
             */
            #[Override]
            public function top(): DomainFact
            {
                return new DomainFact($this->id(), new Term(TermKind::Top));
            }
            /**
             * @param DomainFact $a Contained fact
             * @param DomainFact $b Containing fact
             * @return bool Flat-lattice inclusion
             */
            #[Override]
            public function lessOrEqual(DomainFact $a, DomainFact $b): bool
            {
                return $a->representation->kind === TermKind::Bottom || $b->representation->kind === TermKind::Top || $a->representation->isConcrete() && $b->representation->isConcrete() && $a->representation->native() === $b->representation->native();
            }
            /**
             * @param DomainFact $a First possibility
             * @param DomainFact $b Second possibility
             * @return DomainFact Inclusive alternative
             */
            #[Override]
            public function join(DomainFact $a, DomainFact $b): DomainFact
            {
                if ($this->lessOrEqual($a, $b)) {
                    return $b;
                }
                return $this->lessOrEqual($b, $a) ? $a : $this->top();
            }
            /**
             * @param DomainFact $previous Previous approximation
             * @param DomainFact $next New approximation
             * @return DomainFact Finite widening
             */
            #[Override]
            public function widen(DomainFact $previous, DomainFact $next): DomainFact
            {
                return $this->join($previous, $next);
            }
            /**
             * @param DomainFact $value Policy record
             * @param Projection $projection Selected fields
             * @return DomainFact Projected fact
             */
            #[Override]
            public function project(DomainFact $value, Projection $projection): DomainFact
            {
                $term = $value->representation;
                foreach ($projection->path as $key) {
                    $term = $term->operands[$key] ?? new Term(TermKind::Top);
                }
                return new DomainFact($this->id(), $term);
            }
        }]));
    }
}
