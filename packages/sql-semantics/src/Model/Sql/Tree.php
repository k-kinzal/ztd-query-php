<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Sql;

use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * A complete immutable SQL structure, independent of parser source and formatting.
 *
 * @example Working with SQL structure
 *     $tree = new \SqlSemantics\Model\Sql\Tree('command', [new \SqlSemantics\Model\Sql\Atom('keyword', 'BEGIN')]);
 *     $tree->toString() // => 'BEGIN'
 *
 * @visibility SqlSemantics
 */
final class Tree
{
    /**
     * @param list<Tree|Atom> $children Ordered components of this SQL production
     * @throws InvalidStructure
     */
    public function __construct(public readonly string $role, public readonly array $children)
    {
        \SqlSemantics\Model\Validation\Collections::components($children);
    }

    /**
     * @return list<Atom> SQL terminals in serialization order
     */
    public function atoms(): array
    {
        $result = [];
        foreach ($this->children as $child) {
            array_push($result, ...($child instanceof Atom ? [$child] : $child->atoms()));
        }
        return $result;
    }

    /**
     * Writes the standard compact layout.
     */
    public function toString(): string
    {
        return Format::write($this->atoms());
    }
}
