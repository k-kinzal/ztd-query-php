<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Request;

use Deriver\Exception\InvalidInputException;

/**
 * Separates arbitrary valid callable inputs from declared application entries.
 *
 * @visibility root
 * @example Selecting symbolic inputs
 *     \Deriver\Deriver\Evaluator\Request\QueryScope::symbolic()->mode === \Deriver\Deriver\Evaluator\Request\ScopeMode::Symbolic // => true
 */
final class QueryScope
{
    /**
     * @param ScopeMode $mode Symbolic or entrypoint analysis
     * @param list<EntryPoint> $entries Declared application entries
     * @param CallPath|null $path Optional restriction on the selected invocation's ancestors
     */
    private function __construct(public readonly ScopeMode $mode, public readonly array $entries, public readonly ?CallPath $path = null)
    {
    }

    /**
     * Analyzes all valid inputs to the selected callable.
     * @return self The symbolic scope
     */
    public static function symbolic(): self
    {
        return new self(ScopeMode::Symbolic, []);
    }

    /**
     * Selects invocation contexts through a caller path, leaving its outer prefix open.
     */
    public static function through(CallPath $path): self
    {
        return new self(ScopeMode::Symbolic, [], $path);
    }

    /**
     * Restricts analysis to explicitly declared entries.
     * @param list<EntryPoint> $entries Initial application entries
     * @return self The entrypoint scope
     * @throws InvalidInputException If no entry is supplied
     */
    public static function fromEntrypoints(array $entries): self
    {
        if ($entries === []) {
            throw new InvalidInputException('Entrypoint analysis requires at least one entry.');
        }
        return new self(ScopeMode::Entrypoint, $entries);
    }
}
