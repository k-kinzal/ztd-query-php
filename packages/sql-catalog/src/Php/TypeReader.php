<?php

declare(strict_types=1);

namespace SqlCatalog\Php;

use PhpParser\Node\ComplexType;
use PhpParser\Node\Identifier;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\UnionType;
use SqlCatalog\Type\TypeShape;

/**
 * Reads a written PHP type declaration into the shape the analyzer reasons about.
 *
 * @visibility root
 */
final class TypeReader
{
    /**
     * The shape of a declared type, or the unknown shape when none is written.
     */
    public function read(Identifier|Name|ComplexType|null $type): TypeShape
    {
        if ($type === null) {
            return TypeShape::unknown();
        }
        if ($type instanceof Identifier) {
            return TypeShape::of([$type->toString()]);
        }
        if ($type instanceof Name) {
            return TypeShape::of([$type->toString()]);
        }
        if ($type instanceof NullableType) {
            return $this->read($type->type)->union(TypeShape::of(['null']));
        }
        if ($type instanceof UnionType) {
            return $this->readParts($type->types);
        }
        if ($type instanceof IntersectionType) {
            return $this->readParts($type->types);
        }

        return TypeShape::unknown();
    }

    /**
     * The shape covering every written alternative.
     *
     * @param array<array-key, Identifier|Name|ComplexType> $parts
     */
    public function readParts(array $parts): TypeShape
    {
        $shape = null;
        foreach ($parts as $part) {
            $read = $this->read($part);
            $shape = $shape === null ? $read : $shape->union($read);
        }

        return $shape ?? TypeShape::unknown();
    }
}
