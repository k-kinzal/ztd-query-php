<?php

declare(strict_types=1);

namespace Deriver\Internal\Model;

use Deriver\Api\InvalidInputException;
use Deriver\Api\Project\TargetProfile;
use Deriver\Model\CallDescription;
use Deriver\Model\CallModel;
use Deriver\Model\Contract\DomainLaws;
use Deriver\Model\Domain\AbstractDomain;
use Deriver\Model\Intrinsic\IntrinsicDescriptor;
use Deriver\Model\Intrinsic\PureIntrinsic;
use Deriver\Model\ModelDecision;
use Deriver\Model\ModelDescriptor;
use Deriver\Value\Term;
use Throwable;

/**
 * Converts failures of explicitly trusted plugin callbacks into contract diagnostics.
 * @visibility root
 */
final class ModelBoundary
{
    /**
     * Calls a trusted model without adopting a thrown plugin failure as PHP behavior.
     * @param CallModel $model Registered trusted model
     * @param CallDescription $call Call metadata
     * @return ModelDecision Plan or model-contract failure
     */
    public function describe(CallModel $model, CallDescription $call): ModelDecision
    {
        try {
            return $model->describe($call);
        } catch (Throwable $error) {
            return ModelDecision::unsupported('MODEL_CONTRACT_VIOLATION:' . $error::class, false);
        }
    }

    /**
     * Captures model registration metadata at the trusted plugin boundary.
     * @param CallModel $model Trusted model
     * @return ModelDescriptor Stable registration
     * @throws InvalidInputException If metadata cannot be obtained
     */
    public function descriptor(CallModel $model): ModelDescriptor
    {
        try {
            return $model->descriptor();
        } catch (Throwable $error) {
            throw new InvalidInputException('MODEL_CONTRACT_VIOLATION: descriptor ' . $error::class);
        }
    }

    /**
     * Captures pure-operation metadata without exposing a plugin exception.
     * @param PureIntrinsic $intrinsic Trusted intrinsic
     * @return IntrinsicDescriptor Stable operation contract
     * @throws InvalidInputException If metadata cannot be obtained
     */
    public function intrinsicDescriptor(PureIntrinsic $intrinsic): IntrinsicDescriptor
    {
        try {
            return $intrinsic->descriptor();
        } catch (Throwable $error) {
            throw new InvalidInputException('MODEL_CONTRACT_VIOLATION: intrinsic descriptor ' . $error::class);
        }
    }

    /**
     * Checks basic domain laws before its results can enter the solver.
     * @param AbstractDomain $domain Trusted domain
     * @return array{string, string} Identity and semantic version
     * @throws InvalidInputException If basic lattice laws fail
     */
    public function domainRegistration(AbstractDomain $domain): array
    {
        try {
            $violations = (new DomainLaws())->violations($domain, [$domain->bottom(), $domain->top()]);
            if ($violations !== []) {
                throw new InvalidInputException('MODEL_CONTRACT_VIOLATION: ' . implode(', ', $violations));
            }
            return [$domain->id(), $domain->version()];
        } catch (Throwable $error) {
            throw new InvalidInputException('MODEL_CONTRACT_VIOLATION: domain registration ' . $error::class);
        }
    }

    /**
     * Applies a trusted pure intrinsic while containing plugin exceptions.
     * @param PureIntrinsic $intrinsic Registered operation
     * @param list<Term> $arguments Abstract inputs
     * @param TargetProfile $target PHP target
     * @return Term Abstract result or a contract residual
     */
    public function evaluate(PureIntrinsic $intrinsic, array $arguments, TargetProfile $target): Term
    {
        try {
            if (count($arguments) !== $intrinsic->descriptor()->arity) {
                return Term::opaque('MODEL_CONTRACT_VIOLATION', dependencies: $arguments);
            }
            $result = $intrinsic->evaluate($arguments, $target);
            foreach ($arguments as $argument) {
                if ($argument->isSecret()) {
                    return new Term($result->kind, $result->literal, $result->operands, $result->attributes, true);
                }
            }
            return $result;
        } catch (Throwable $error) {
            return new Term('opaque', 'MODEL_CONTRACT_VIOLATION', $arguments, ['type' => 'mixed', 'failureClass' => $error::class]);
        }
    }
    /**
     * Captures a trusted provider contribution and classifies plugin failures.
     * @param \Deriver\Model\Provider\Provider $provider Registered provider
     * @return array{string, string} Captured contribution
     * @throws InvalidInputException If the provider violates its contract
     */
    public function providerRegistration(\Deriver\Model\Provider\Provider $provider): array
    {
        try {
            return [$provider->id(), $provider->version()];
        } catch (Throwable $error) {
            throw new InvalidInputException('MODEL_CONTRACT_VIOLATION: provider providerRegistration ' . $error::class);
        }
    }

    /**
     * Captures a trusted provider contribution and classifies plugin failures.
     * @param \Deriver\Model\Provider\DeclarationProvider $provider Registered provider
     * @return \Deriver\Api\Project\ProjectInput Captured contribution
     * @throws InvalidInputException If the provider violates its contract
     */
    public function declarations(\Deriver\Model\Provider\DeclarationProvider $provider): \Deriver\Api\Project\ProjectInput
    {
        try {
            return $provider->declarations();
        } catch (Throwable $error) {
            throw new InvalidInputException('MODEL_CONTRACT_VIOLATION: provider declarations ' . $error::class);
        }
    }

    /**
     * Captures a trusted provider contribution and classifies plugin failures.
     * @param \Deriver\Model\Provider\EnvironmentProvider $provider Registered provider
     * @return array<string, Term> Captured contribution
     * @throws InvalidInputException If the provider violates its contract
     */
    public function environment(\Deriver\Model\Provider\EnvironmentProvider $provider): array
    {
        try {
            return $provider->environment();
        } catch (Throwable $error) {
            throw new InvalidInputException('MODEL_CONTRACT_VIOLATION: provider environment ' . $error::class);
        }
    }

    /**
     * Captures a trusted provider contribution and classifies plugin failures.
     * @param \Deriver\Model\Provider\EntryPointProvider $provider Registered provider
     * @return list<\Deriver\Api\Project\EntryPoint> Captured contribution
     * @throws InvalidInputException If the provider violates its contract
     */
    public function entries(\Deriver\Model\Provider\EntryPointProvider $provider): array
    {
        try {
            return $provider->entries();
        } catch (Throwable $error) {
            throw new InvalidInputException('MODEL_CONTRACT_VIOLATION: provider entries ' . $error::class);
        }
    }

    /**
     * Captures a trusted provider contribution and classifies plugin failures.
     * @param \Deriver\Model\Provider\DomainProvider $provider Registered provider
     * @return list<AbstractDomain> Captured contribution
     * @throws InvalidInputException If the provider violates its contract
     */
    public function domains(\Deriver\Model\Provider\DomainProvider $provider): array
    {
        try {
            return $provider->domains();
        } catch (Throwable $error) {
            throw new InvalidInputException('MODEL_CONTRACT_VIOLATION: provider domains ' . $error::class);
        }
    }

    /**
     * Captures a trusted provider contribution and classifies plugin failures.
     * @param \Deriver\Model\Provider\ObservationProvider $provider Registered provider
     * @param \Deriver\Api\AnalysisSession $session Captured source session
     * @return array<string, \Deriver\Api\Query\Query> Captured contribution
     * @throws InvalidInputException If the provider violates its contract
     */
    public function queries(\Deriver\Model\Provider\ObservationProvider $provider, \Deriver\Api\AnalysisSession $session): array
    {
        try {
            return $provider->queries($session);
        } catch (Throwable $error) {
            throw new InvalidInputException('MODEL_CONTRACT_VIOLATION: provider queries ' . $error::class);
        }
    }

    /**
     * Resolves a trusted dispatch contract without running application code.
     * @param \Deriver\Model\Provider\DispatchProvider $provider Registered provider
     * @param \Deriver\Model\Provider\DispatchRequest $request Evaluated call metadata
     * @return \Deriver\Model\Provider\DispatchDecision|null Decision or contract failure
     */
    public function dispatch(\Deriver\Model\Provider\DispatchProvider $provider, \Deriver\Model\Provider\DispatchRequest $request): ?\Deriver\Model\Provider\DispatchDecision
    {
        try {
            return $provider->resolve($request);
        } catch (Throwable $error) {
            return null;
        }
    }

    /**
     * Obtains a trusted conditional implication with an explicit failure value.
     * @param \Deriver\Model\Provider\RefinementModel $provider Registered predicate model
     * @param Term $predicate Evaluated branch predicate
     * @param bool $truth Observed branch polarity
     * @return Term|null Guaranteed implication or no refinement
     */
    public function refinement(\Deriver\Model\Provider\RefinementModel $provider, Term $predicate, bool $truth): ?Term
    {
        try {
            return $provider->refine($predicate, $truth);
        } catch (Throwable $error) {
            return Term::opaque('MODEL_CONTRACT_VIOLATION');
        }
    }
    /**
     * Checks a registered domain operation before adopting its fact.
     * @param AbstractDomain $domain Registered lattice
     * @param string $operation join, widen, or project
     * @param Term $left First encoded domain fact
     * @param Term|null $right Second fact for join or widening
     * @param \Deriver\Value\Projection $projection Requested fields
     * @return Term Checked domain result or a contract failure
     */
    public function domain(AbstractDomain $domain, string $operation, Term $left, ?Term $right = null, \Deriver\Value\Projection $projection = new \Deriver\Value\Projection()): Term
    {
        $inputs = $right === null ? [$left] : [$left, $right];
        try {
            $a = new \Deriver\Model\Domain\DomainFact($domain->id(), $left->operands['representation'] ?? Term::opaque('INVALID_DOMAIN_FACT'));
            $b = new \Deriver\Model\Domain\DomainFact($domain->id(), $right?->operands['representation'] ?? $a->representation);
            $result = match ($operation) {
                'join' => $domain->join($a, $b),
                'widen' => $domain->widen($a, $b),
                'project' => $domain->project($a, $projection),
                default => throw new InvalidInputException('Unknown domain operation.'),
            };
            if ($result->domain !== $domain->id() || ($operation !== 'project' && (!$domain->lessOrEqual($a, $result) || !$domain->lessOrEqual($b, $result)))) {
                return Term::opaque('MODEL_CONTRACT_VIOLATION', dependencies: $inputs);
            }
            $encoded = $result->term();
            return $left->isSecret() || ($right?->isSecret() ?? false) ? new Term($encoded->kind, $encoded->literal, $encoded->operands, $encoded->attributes, true) : $encoded;
        } catch (Throwable $error) {
            return Term::opaque('MODEL_CONTRACT_VIOLATION', dependencies: $inputs);
        }
    }

    /**
     * Checks domain inclusion without treating a plugin exception as convergence.
     * @param AbstractDomain $domain Registered lattice
     * @param Term $upper Candidate containing fact
     * @param Term $lower Candidate contained fact
     * @return bool Whether the provider establishes inclusion
     */
    public function contains(AbstractDomain $domain, Term $upper, Term $lower): bool
    {
        try {
            return $domain->lessOrEqual(new \Deriver\Model\Domain\DomainFact($domain->id(), $lower->operands['representation'] ?? Term::opaque('INVALID_DOMAIN_FACT')), new \Deriver\Model\Domain\DomainFact($domain->id(), $upper->operands['representation'] ?? Term::opaque('INVALID_DOMAIN_FACT')));
        } catch (Throwable $error) {
            return false;
        }
    }
}
