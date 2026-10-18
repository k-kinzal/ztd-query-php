<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Expression;

use Deriver\Deriver\Evaluator\Term;
use Deriver\Deriver\Evaluator\TermKind;

/**
 * Resolves named environment inputs without reading the host process environment.
 * @visibility parent
 */
final class EnvironmentEvaluator
{
    /**
     * @param array<string, Term> $environment Explicit variable and environment inputs
     */
    public function __construct(private readonly array $environment)
    {
    }

    /**
     * Resolves a named getenv lookup; an absent name remains an external dependency.
     * A partial input map cannot establish the complete environment returned by getenv().
     * @param list<Term> $arguments Evaluated getenv arguments
     */
    public function evaluate(array $arguments): ?Term
    {
        $name = $arguments[0] ?? null;
        if ($name?->kind !== TermKind::Constant || !is_string($name->literal) || str_starts_with($name->literal, '$')) {
            return null;
        }
        return $this->environment[$name->literal] ?? null;
    }
}
