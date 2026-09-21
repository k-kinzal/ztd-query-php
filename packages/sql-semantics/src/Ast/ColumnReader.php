<?php

declare(strict_types=1);

namespace SqlSemantics\Ast;

use SqlParser\Parser\Node;
use SqlSemantics\Schema\ColumnDefinition;
use SqlSemantics\Schema\TableConstraint;
use SqlSemantics\Type\Nullability;

/**
 * Reads declaration-level nullability, defaults, and column constraints.
 *
 * @visibility SqlSemantics
 */
final class ColumnReader
{
    /**
     * Binds the dependencies used for semantic binding.
     */
    public function __construct(public readonly Identifiers $identifiers)
    {
    }

    /**
     * @param list<Node> $attributes
     * @return array{ColumnDefinition, list<TableConstraint>}
     */
    public function read(Node $node, array $attributes): array
    {
        $nameNode = Tree::child($node, ['ColId', 'ident', 'nm']);
        $typeNode = Tree::outer($node, ['Typename', 'type', 'typetoken'])[0] ?? null;
        if ($nameNode === null || $typeNode === null) {
            Tree::unsupported($node, 'column declaration');
        }
        $name = $this->identifiers->parts($nameNode)[0];
        $type = (new TypeReader($this->identifiers->dialect))->read($typeNode);
        $nullability = Nullability::MaybeNull;
        $default = null;
        $constraints = [];
        foreach ($attributes as $attribute) {
            $constraint = (new ConstraintReader($this->identifiers))->read($attribute, $name);
            if ($constraint !== null) {
                $constraints[] = $constraint;
                continue;
            }
            $text = strtoupper(Tree::text($attribute));
            if ($text === 'NOT NULL') {
                $nullability = Nullability::NotNull;
            } elseif (str_starts_with($text, 'DEFAULT ')) {
                $default = $attribute;
            } elseif ($text !== 'NULL') {
                Tree::unsupported($attribute, 'column attribute');
            }
        }

        return [new ColumnDefinition($name, $type, $nullability, $node, $default), $constraints];
    }
}
