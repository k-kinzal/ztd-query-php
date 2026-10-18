<?php

declare (strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Domain;

use Deriver\Deriver\Evaluator\Domain\AbstractDomain;
use Deriver\Deriver\Evaluator\Domain\DomainFact;
use Deriver\Deriver\Evaluator\Projection;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Evaluator\TermKind;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Deriver\Evaluator\Domain\AbstractDomain
 */
#[CoversClass(AbstractDomain::class)]
#[UsesClass(DomainFact::class)]
#[UsesClass(Projection::class)]
#[UsesClass(Term::class)]
#[Small]
final class AbstractDomainTest extends TestCase
{
    public function testIdNamesTheDomain(): void
    {
        self::assertSame('example.policy', (new class () implements AbstractDomain {
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
        })->id());
    }
    public function testVersionNamesTheSemanticRevision(): void
    {
        self::assertSame('1', (new class () implements AbstractDomain {
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
        })->version());
    }
    public function testBottomRepresentsNoPossibilities(): void
    {
        self::assertSame(TermKind::Bottom, (new class () implements AbstractDomain {
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
        })->bottom()->representation->kind);
    }
    public function testTopRepresentsEveryPolicy(): void
    {
        self::assertSame(TermKind::Top, (new class () implements AbstractDomain {
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
        })->top()->representation->kind);
    }
    public function testLessOrEqualIncludesBottomAndRejectsReverseInclusion(): void
    {
        $domain = new class () implements AbstractDomain {
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
        };
        self::assertTrue($domain->lessOrEqual($domain->bottom(), $domain->top()));
        self::assertFalse($domain->lessOrEqual($domain->top(), $domain->bottom()));
    }
    public function testJoinPreservesBothDisjointRecords(): void
    {
        $domain = new class () implements AbstractDomain {
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
        };
        $a = new DomainFact($domain->id(), Term::fromNative(['ttl' => 1]));
        $b = new DomainFact($domain->id(), Term::fromNative(['ttl' => 2]));
        $join = $domain->join($a, $b);
        self::assertTrue($domain->lessOrEqual($a, $join));
        self::assertTrue($domain->lessOrEqual($b, $join));
    }
    public function testWidenIncludesThePreviousAndNextApproximation(): void
    {
        $domain = new class () implements AbstractDomain {
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
        };
        self::assertSame(TermKind::Top, $domain->widen($domain->bottom(), $domain->top())->representation->kind);
    }
    public function testProjectSelectsOneKnownPolicyField(): void
    {
        $domain = new class () implements AbstractDomain {
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
        };
        $fact = new DomainFact($domain->id(), Term::fromNative(['ttl' => 3]));
        self::assertSame(3, $domain->project($fact, new Projection(['ttl']))->representation->native());
    }
}
