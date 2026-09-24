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
        $generated = self::generatedExpression($node);
        foreach ((new ConstraintGroups())->read($attributes) as $attribute) {
            $constraint = (new ConstraintReader($this->identifiers))->read($attribute, $name);
            if ($constraint !== null) {
                $constraints[] = $constraint;
                continue;
            }
            $words = self::attributeWords($attribute);
            $text = implode(' ', $words);
            if (str_starts_with($text, 'NOT NULL') || in_array('IDENTITY', $words, true) || $text === 'AUTO_INCREMENT') {
                $nullability = Nullability::NotNull;
            } elseif (($words[0] ?? '') === 'DEFAULT') {
                $default = $attribute;
            } elseif (in_array('GENERATED', $words, true) || ($words[0] ?? '') === 'AS') {
                $generated = Tree::outer($attribute, ['a_expr', 'expr'])[0] ?? $attribute;
            }
        }

        return [new ColumnDefinition($name, $type, $nullability, $node, $default, $attributes, $generated, Definition\OptionReader::column($node, $attributes, $this->identifiers)), $constraints];
    }

    /**
     * Finds the MySQL generation expression written after the column type (`AS (expression)`), in both the 5.7 and the 8.x shapes.
     */
    public static function generatedExpression(Node $column): ?Node
    {
        $field = Tree::child(Tree::child($column, ['field_spec']) ?? $column, ['field_def']);
        return $field === null ? null : Tree::child(Tree::child($field, ['generated_column_func']) ?? $field, ['expr']);
    }

    /**
     * Returns the uppercase words of a column attribute outside its expressions and without a leading CONSTRAINT name.
     * @return list<string>
     */
    public static function attributeWords(Node $attribute): array
    {
        $words = Tree::keywords($attribute);
        return ($words[0] ?? '') === 'CONSTRAINT' ? array_slice($words, 2) : $words;
    }
}
