<?php

declare(strict_types=1);

namespace Deriver\Result;

use Deriver\Value\Term;

/**
 * Immutable storage graph preserving object identity, reference aliases, and cycles.
 *
 * Bindings are location terms whose literal identifies a cell and whose operands
 * contain its ordered path keys. Object terms identify an object:<literal> cell;
 * cell terms directly identify a cell. Identities are local to this alternative.
 *
 * @visibility public
 * @example Inspecting an empty storage graph
 *     (new \Deriver\Result\StorageSnapshot())->cells // => []
 */
final class StorageSnapshot
{
    /**
     * @param array<string, Term> $bindings Local names and their location terms
     * @param array<string, Term> $cells Reachable raw cells, objects, globals, statics, and model slots
     */
    public function __construct(public readonly array $bindings = [], public readonly array $cells = [])
    {
    }
}
