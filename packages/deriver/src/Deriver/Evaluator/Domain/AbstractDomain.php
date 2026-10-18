<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Domain;

use Deriver\Deriver\Evaluator\Projection;

/**
 * A versioned abstract lattice; join is inclusion, not application-specific composition.
 *
 * @visibility root
 * @example Naming a domain fact
 *     (new \Deriver\Deriver\Evaluator\Domain\DomainFact('policy', \Deriver\Deriver\Evaluator\Term::array([])))->domain // => 'policy'
 */
interface AbstractDomain
{
    /**
     * @return string Stable domain identifier.
     * @throws \Deriver\Exception\EvaluationException If trusted plugin code fails
     */
    public function id(): string;
    /**
     * @return string Semantic version recorded in the evaluator manifest.
     * @throws \Deriver\Exception\EvaluationException If trusted plugin code fails
     */
    public function version(): string;
    /**
     * @return DomainFact No concrete possibilities.
     * @throws \Deriver\Exception\EvaluationException If trusted plugin code fails
     */
    public function bottom(): DomainFact;
    /**
     * @return DomainFact Every domain possibility.
     * @throws \Deriver\Exception\EvaluationException If trusted plugin code fails
     */
    public function top(): DomainFact;
    /**
     * @param DomainFact $a Candidate contained fact
     * @param DomainFact $b Candidate containing fact
     * @return bool Whether a is included in b
     * @throws \Deriver\Exception\EvaluationException If trusted plugin code fails
     */
    public function lessOrEqual(DomainFact $a, DomainFact $b): bool;
    /**
     * @param DomainFact $a First alternative
     * @param DomainFact $b Second alternative
     * @return DomainFact Inclusive join
     * @throws \Deriver\Exception\EvaluationException If trusted plugin code fails
     */
    public function join(DomainFact $a, DomainFact $b): DomainFact;
    /**
     * @param DomainFact $previous Previous approximation
     * @param DomainFact $next Next approximation
     * @return DomainFact Finite convergent upper bound
     * @throws \Deriver\Exception\EvaluationException If trusted plugin code fails
     */
    public function widen(DomainFact $previous, DomainFact $next): DomainFact;
    /**
     * @param DomainFact $value Structured fact
     * @param Projection $projection Required field path
     * @return DomainFact Projected fact
     * @throws \Deriver\Exception\EvaluationException If trusted plugin code fails
     */
    public function project(DomainFact $value, Projection $projection): DomainFact;
}
