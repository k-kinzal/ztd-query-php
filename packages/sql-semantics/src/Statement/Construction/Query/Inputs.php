<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Construction\Query;

/**
 * All named input positions of a new query, in order.
 * @visibility public
 * @example Specifying new input before name resolution
 *     $input = new \SqlSemantics\Statement\Construction\Query\Inputs();
 *     $input->items // => []
 */
final class Inputs
{
    use \SqlSemantics\Statement\Validation\Snapshot;

    /**
     * @var list<NamedInput>
     */
    public readonly array $items;

    /**
     * Keeps the complete supplied sequence, including duplicate values.
     */
    public function __construct(NamedInput ...$items)
    {
        $this->items = array_values($items);
    }
}
