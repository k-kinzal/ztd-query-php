<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Predicate;

use Closure;
use Deriver\Deriver\Evaluator\Enumeration\Choices;
use Deriver\Deriver\Evaluator\Evidence\Provenance;
use Deriver\Deriver\Evaluator\Scalar\Operations;
use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Evaluator\TermKind;

/**
 * Projects candidate alternatives onto a bounded boolean domain.
 * Unknown dependencies remain visible and never justify removing a branch.
 * @visibility root
 */
final class Projection
{
    /**
     * Preserves the evidence needed to establish each boolean possibility.
     */
    public function __construct(private readonly Provenance $provenance = new Provenance(), private readonly ?\Deriver\Deriver\Evaluator\Enumeration\Finite\Demand $finite = null)
    {
    }

    /**
     * Converts candidate values using PHP boolean conversion.
     */
    public function value(Term $value): Term
    {
        return $this->collect([static fn (): Term => $value]);
    }

    /**
     * Stops opening alternatives once both boolean outcomes have witnesses.
     * @param iterable<int, Closure(int=): Term> $branches Lazy candidate dependencies
     */
    public function collect(iterable $branches, int $wanted = 3): Term
    {
        if ($this->finite !== null) {
            return $this->finite->collect($branches, $this->provenance);
        }
        $known = [];
        $unknown = [];
        $found = 0;
        $choices = new Choices($this->provenance);
        foreach ($branches as $branch) {
            foreach ($choices->alternatives($branch($wanted & ~$found)) as [$value]) {
                $truth = (new Operations())->truth($value);
                if ($truth === null) {
                    $unknown[] = [new Term(TermKind::Operation, 'truth', [$value]), []];
                } else {
                    $found |= $truth ? 2 : 1;
                    $known[(int) $truth] ??= [$this->provenance->operation(Term::constant($truth), [$value], 'truth'), []];
                }
                if (($found & $wanted) === $wanted) {
                    return $choices->make(array_values($known));
                }
            }
        }
        return $choices->make([...array_values($known), ...$unknown]);
    }
}
