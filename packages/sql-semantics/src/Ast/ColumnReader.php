<?php

declare(strict_types=1);

namespace SqlSemantics\Ast;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Declaration\ColumnDefinition;
use SqlSemantics\Ast\Declaration\TableConstraint;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

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
        $nameNode = Tree::outer($node, ['ColId', 'ident', 'nm', 'field_ident'])[0] ?? null;
        $typeNode = Tree::outer($node, ['Typename', 'type', 'typetoken'])[0] ?? null;
        if ($nameNode === null) {
            Tree::invalid($node, 'column declaration');
        }
        $name = $this->identifiers->parts($nameNode)[0];
        $type = $typeNode === null ? new TypeDescriptor($this->identifiers->dialect, new \SqlSemantics\Type\Identity\SqliteDeclaration('', \SqlSemantics\Type\Identity\StorageAffinity::Blob)) : (new TypeReader($this->identifiers->dialect))->read($typeNode);
        $nullability = Nullability::MaybeNull;
        $default = null;
        $constraints = [];
        $field = Tree::child($node, ['field_def']);
        $generated = $field === null ? null : Tree::child($field, ['expr']);
        foreach ((new ConstraintGroups())->read($attributes) as $attribute) {
            $constraint = (new ConstraintReader($this->identifiers))->read($attribute, $name);
            if ($constraint !== null) {
                $constraints[] = $constraint;
                continue;
            }
            $tokens = $attribute->tokens();
            if (strtoupper($tokens[0]->text) === 'CONSTRAINT') {
                $tokens = array_slice($tokens, 2);
            }
            $text = strtoupper(implode(' ', array_map(static fn ($token): string => $token->text, $tokens)));
            if (str_starts_with($text, 'NOT NULL') || str_contains($text, 'IDENTITY') || $text === 'AUTO_INCREMENT') {
                $nullability = Nullability::NotNull;
            } elseif (str_starts_with($text, 'DEFAULT ')) {
                $default = $attribute;
            } elseif (str_contains($text, 'GENERATED') || str_starts_with($text, 'AS ')) {
                $generated = Tree::outer($attribute, ['a_expr', 'expr'])[0] ?? $attribute;
            }
        }

        return [new ColumnDefinition($name, $type, $nullability, $node, $default, $attributes, $generated, Definition\OptionReader::column($node, $attributes, $this->identifiers)), $constraints];
    }
}
