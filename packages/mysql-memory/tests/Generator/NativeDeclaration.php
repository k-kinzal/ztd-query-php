<?php

declare(strict_types=1);

namespace Tests\Generator;

use PhpParser\Node;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\NullableTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\PhpDocParser\Ast\Type\UnionTypeNode;
use RuntimeException;

/**
 * Converts declared PHP types into the same input model used for PHPDoc types.
 */
final class NativeDeclaration
{
    /**
     * @throws RuntimeException When a constructor declares an unsupported intersection
     */
    public function node(Node\Identifier|Node\Name|Node\ComplexType $type): TypeNode
    {
        if ($type instanceof Node\NullableType) {
            return new NullableTypeNode($this->node($type->type));
        }
        if ($type instanceof Node\UnionType) {
            return new UnionTypeNode(array_map($this->node(...), $type->types));
        }
        if ($type instanceof Node\Identifier || $type instanceof Node\Name) {
            return new IdentifierTypeNode(($type instanceof Node\Name\FullyQualified ? '\\' : '') . $type->toString());
        }
        throw new RuntimeException('Unsupported constructor intersection: ' . $type->getType());
    }
}
