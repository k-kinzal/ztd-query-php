<?php

declare (strict_types=1);

namespace Tests\Unit\Deriver\Providers;

use Deriver\Deriver\Contributions;
use Deriver\Deriver\DeriverConfig;
use Deriver\Deriver\Evaluator\Call\Provider\DispatchDecision;
use Deriver\Deriver\Evaluator\Call\Provider\DispatchProvider;
use Deriver\Deriver\Evaluator\Call\Provider\DispatchRequest;
use Deriver\Deriver\Evaluator\Call\Provider\DispatchTarget;
use Deriver\Deriver\Evaluator\Call\Provider\RefinementEvaluator;
use Deriver\Deriver\Evaluator\Domain\AbstractDomain;
use Deriver\Deriver\Evaluator\Domain\DomainFact;
use Deriver\Deriver\Evaluator\Projection;
use Deriver\Deriver\Evaluator\Request\EntryPoint;
use Deriver\Deriver\Evaluator\TargetProfile;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Evaluator\TermKind;
use Deriver\Deriver\Providers\DeclarationProvider;
use Deriver\Deriver\Providers\DomainProvider;
use Deriver\Deriver\Providers\EntryPointProvider;
use Deriver\Deriver\Providers\EnvironmentProvider;
use Deriver\Deriver\Providers\ProviderExpansion;
use Deriver\Deriver\Sources\Path;
use Deriver\Deriver\Sources\Source;
use Deriver\Deriver\Sources\Sources;
use Deriver\Exception\InvalidInputException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Deriver\Deriver\Providers\ProviderExpansion
 */
#[CoversClass(ProviderExpansion::class)]
#[UsesClass(DeriverConfig::class)]
#[UsesClass(EntryPoint::class)]
#[UsesClass(Sources::class)]
#[UsesClass(Path::class)]
#[UsesClass(Source::class)]
#[UsesClass(TargetProfile::class)]
#[UsesClass(Term::class)]
#[Small]
final class ProviderExpansionTest extends TestCase
{
    public function testCapturesContributionsBeforeSnapshotConstruction(): void
    {
        $inputs = new ProviderExpansion(new Sources([]), [], new Contributions(providers: [new class () implements DeclarationProvider, DispatchProvider, EnvironmentProvider, EntryPointProvider {
            /**
             * @return string Provider identity
             */
            #[Override]
            public function id(): string
            {
                return 'example.container';
            }
            /**
             * @return string Captured export version
             */
            #[Override]
            public function version(): string
            {
                return '1';
            }
            /**
             * @return Sources Explicit generated declaration and semantics
             */
            #[Override]
            public function declarations(): Sources
            {
                return new Sources([new Source('generated/service.php', '<?php class GeneratedService{public function label(){return "service";}}')]);
            }
            /**
             * @param DispatchRequest $request Evaluated call metadata
             * @return DispatchDecision Exported binding, with explicit completeness
             */
            #[Override]
            public function resolve(DispatchRequest $request): DispatchDecision
            {
                if ($request->method !== 'label' || ($request->receiver->attributes['type'] ?? '') !== 'Contract') {
                    return new DispatchDecision();
                }
                return new DispatchDecision([new DispatchTarget('GeneratedService::label', new Term(TermKind::Object, 'container:shared-service', attributes: ['class' => 'GeneratedService']))], true, ['container:export-complete']);
            }
            /**
             * @return array<string, Term> Captured settings
             */
            #[Override]
            public function environment(): array
            {
                return ['APP_NAME' => Term::constant('captured')];
            }
            /**
             * @return list<EntryPoint> Declared lifecycle entries
             */
            #[Override]
            public function entries(): array
            {
                return [new EntryPoint('entry')];
            }
        }, new class () implements DomainProvider, RefinementEvaluator {
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
        }]));
        self::assertSame('generated/service.php', $inputs->input[0]->path);
        self::assertSame('captured', $inputs->environment['APP_NAME']->native());
        self::assertSame('entry', $inputs->entries[0]->symbol);
        self::assertCount(1, $inputs->contributions->domains);
    }
    public function testRejectsEnvironmentConflicts(): void
    {
        $this->expectException(InvalidInputException::class);
        new ProviderExpansion(new Sources([]), ['APP_NAME' => Term::constant('conflict')], new Contributions(providers: [new class () implements DeclarationProvider, DispatchProvider, EnvironmentProvider, EntryPointProvider {
            /**
             * @return string Provider identity
             */
            #[Override]
            public function id(): string
            {
                return 'example.container';
            }
            /**
             * @return string Captured export version
             */
            #[Override]
            public function version(): string
            {
                return '1';
            }
            /**
             * @return Sources Explicit generated declaration and semantics
             */
            #[Override]
            public function declarations(): Sources
            {
                return new Sources([new Source('generated/service.php', '<?php class GeneratedService{public function label(){return "service";}}')]);
            }
            /**
             * @param DispatchRequest $request Evaluated call metadata
             * @return DispatchDecision Exported binding, with explicit completeness
             */
            #[Override]
            public function resolve(DispatchRequest $request): DispatchDecision
            {
                if ($request->method !== 'label' || ($request->receiver->attributes['type'] ?? '') !== 'Contract') {
                    return new DispatchDecision();
                }
                return new DispatchDecision([new DispatchTarget('GeneratedService::label', new Term(TermKind::Object, 'container:shared-service', attributes: ['class' => 'GeneratedService']))], true, ['container:export-complete']);
            }
            /**
             * @return array<string, Term> Captured settings
             */
            #[Override]
            public function environment(): array
            {
                return ['APP_NAME' => Term::constant('captured')];
            }
            /**
             * @return list<EntryPoint> Declared lifecycle entries
             */
            #[Override]
            public function entries(): array
            {
                return [new EntryPoint('entry')];
            }
        }]));
    }
}
