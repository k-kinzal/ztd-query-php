<?php

declare(strict_types=1);

namespace Deriver\Query;

use Deriver\Exception\InvalidInputException;
use Deriver\Project\EntryPoint;

/**
 * Separates arbitrary valid callable inputs from declared application entries.
 *
 * @visibility public
 * @example Selecting symbolic inputs
 *     \Deriver\Query\QueryScope::symbolic()->mode // => 'symbolic'
 */
final class QueryScope
{
    /**
     * @param string $mode Symbolic or entrypoint analysis
     * @param list<EntryPoint> $entries Declared application entries
     */
    private function __construct(public readonly string $mode, public readonly array $entries)
    {
    }

    /**
     * Analyzes all valid inputs to the selected callable.
     * @return self The symbolic scope
     */
    public static function symbolic(): self
    {
        return new self('symbolic', []);
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
        return new self('entrypoint', $entries);
    }
}
