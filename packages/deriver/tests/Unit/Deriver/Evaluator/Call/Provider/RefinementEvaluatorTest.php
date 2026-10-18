<?php

declare (strict_types=1);

namespace Tests\Unit\Deriver\Evaluator\Call\Provider;

use Deriver\Deriver\Evaluator\Call\Provider\RefinementEvaluator;
use Deriver\Deriver\Evaluator\Domain\AbstractDomain;
use Deriver\Deriver\Evaluator\Domain\DomainFact;
use Deriver\Deriver\Evaluator\Projection;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Evaluator\TermKind;
use Deriver\Deriver\Providers\DomainProvider;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Deriver\Evaluator\Call\Provider\RefinementEvaluator
 */
#[CoversClass(RefinementEvaluator::class)]
#[UsesClass(Term::class)]
#[Small]
final class RefinementEvaluatorTest extends TestCase
{
    public function testRefineReturnsAGuaranteedImplicationOfTheTrueBranch(): void
    {
        $predicate = Term::parameter('flag', 'bool');
        self::assertSame($predicate, (new class () implements DomainProvider, RefinementEvaluator {
            /**
             * @return string Stable provider identity
             */
            #[Override]
            public function id(): string
            {
                return 'example.policy-provider';
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
             * @return list<AbstractDomain> Captured domains
             */
            #[Override]
            public function domains(): array
            {
                return [new class () implements AbstractDomain {
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
                }];
            }
            /**
             * @param Term $predicate Branch predicate
             * @param bool $truth Selected polarity
             * @return Term Guaranteed implication
             */
            #[Override]
            public function refine(Term $predicate, bool $truth): Term
            {
                return $truth ? $predicate : new Term(TermKind::Unary, '!', [$predicate]);
            }
        })->refine($predicate, true));
    }
}
