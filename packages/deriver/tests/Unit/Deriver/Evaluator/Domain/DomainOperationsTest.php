<?php

declare (strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Domain;

use Deriver\Deriver\Evaluator\Domain\AbstractDomain;
use Deriver\Deriver\Evaluator\Domain\DomainFact;
use Deriver\Deriver\Evaluator\Domain\DomainLaws;
use Deriver\Deriver\Evaluator\Domain\DomainOperations;
use Deriver\Deriver\Evaluator\Projection;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Evaluator\TermKind;
use Deriver\Exception\EvaluationContractException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(DomainOperations::class)]
#[UsesClass(DomainLaws::class)]
#[UsesClass(DomainFact::class)]
#[UsesClass(Projection::class)]
#[UsesClass(Term::class)]
#[Small]
final class DomainOperationsTest extends TestCase
{
    public function testRegistrationChecksTheLatticeBeforeAcceptingItsIdentity(): void
    {
        self::assertSame(['example.policy', '1'], (new DomainOperations())->registration(new class () implements AbstractDomain {
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
        }));
    }
    public function testApplyJoinContainsBothInputFacts(): void
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
        $value = (new DomainOperations())->apply($domain, 'join', $domain->bottom()->term(), $domain->top()->term());
        self::assertSame(TermKind::Top, $value->operands['representation']->kind);
    }
    public function testContainsRequiresTheDeclaredInclusionDirection(): void
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
        $boundary = new DomainOperations();
        self::assertTrue($boundary->contains($domain, $domain->top()->term(), $domain->bottom()->term()));
        self::assertFalse($boundary->contains($domain, $domain->bottom()->term(), $domain->top()->term()));
    }
    public function testRegistrationPropagatesProviderFailures(): void
    {
        $plugin = self::createStub(AbstractDomain::class);
        $plugin->method('bottom')->willThrowException(new RuntimeException('sensitive plugin detail'));
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('sensitive plugin detail');
        (new DomainOperations())->registration($plugin);
    }
    public function testRegistrationRejectsViolationsOfTheDeclaredLaws(): void
    {
        $domain = self::createStub(AbstractDomain::class);
        $fact = new DomainFact('broken', Term::constant(1));
        $domain->method('id')->willReturn('broken');
        $domain->method('bottom')->willReturn($fact);
        $domain->method('top')->willReturn($fact);
        $domain->method('join')->willReturn($fact);
        $domain->method('widen')->willReturn($fact);
        $domain->method('lessOrEqual')->willReturn(false);
        $this->expectException(EvaluationContractException::class);
        $this->expectExceptionMessage('Invalid domain registration:');
        (new DomainOperations())->registration($domain);
    }
    public function testApplyProjectsTheDomainRepresentation(): void
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
        $input = new Term(TermKind::Domain, $domain->id(), ['representation' => Term::fromNative(['x' => 'private'])]);
        $projected = (new DomainOperations())->apply($domain, 'project', $input, projection: new Projection(['x']));
        self::assertSame('private', $projected->operands['representation']->native());
    }
    public function testApplyRejectsFactsOwnedByAnotherDomain(): void
    {
        $domain = self::createStub(AbstractDomain::class);
        $domain->method('id')->willReturn('example');
        $domain->method('project')->willReturn(new DomainFact('different', Term::constant(1)));
        $input = (new DomainFact('example', Term::constant(2)))->term();
        $this->expectException(EvaluationContractException::class);
        (new DomainOperations())->apply($domain, 'project', $input);
    }
    public function testApplyRejectsWideningThatDoesNotContainBothInputs(): void
    {
        $domain = self::createStub(AbstractDomain::class);
        $domain->method('id')->willReturn('example');
        $domain->method('widen')->willReturn(new DomainFact('example', Term::constant(1)));
        $domain->method('lessOrEqual')->willReturn(false);
        $a = (new DomainFact('example', Term::constant(1)))->term();
        $b = (new DomainFact('example', Term::constant(2)))->term();
        $this->expectException(EvaluationContractException::class);
        (new DomainOperations())->apply($domain, 'widen', $a, $b);
    }
    public function testApplyRejectsUnknownOperations(): void
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
        $this->expectException(EvaluationContractException::class);
        (new DomainOperations())->apply($domain, 'invalid', $domain->top()->term());
    }
    public function testApplyPropagatesProviderFailures(): void
    {
        $domain = self::createStub(AbstractDomain::class);
        $domain->method('id')->willReturn('example');
        $failure = new RuntimeException('domain failure');
        $domain->method('join')->willThrowException($failure);
        $this->expectExceptionObject($failure);
        (new DomainOperations())->apply($domain, 'join', (new DomainFact('example', Term::constant(1)))->term());
    }
    public function testContainsCannotTreatAPluginExceptionAsConvergence(): void
    {
        $domain = self::createStub(AbstractDomain::class);
        $domain->method('id')->willReturn('example');
        $domain->method('lessOrEqual')->willThrowException(new RuntimeException());
        $input = (new DomainFact('example', Term::constant(1)))->term();
        $this->expectException(RuntimeException::class);
        (new DomainOperations())->contains($domain, $input, $input);
    }
}
