<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Ast;

use SqlParser\Parser\Node;
use SqlSemantics\Core\Analysis\ValueReader;
use SqlSemantics\Core\Schema\ColumnDefinition;
use SqlSemantics\Core\Schema\TableConstraint;
use SqlSemantics\Core\Type\Nullability;

/**
 * Reads declaration-level nullability, defaults, and column constraints.
 *
 * @visibility SqlSemantics
 */
final class ColumnReader
{
    private readonly ValueReader $values;
    /**
     * Binds the dependencies used for semantic binding.
     */
    public function __construct(public readonly Identifiers $identifiers, ?ValueReader $values = null)
    {
        $this->values = $values ?? $identifiers->dialect->platform()->values((new DialectParser($identifiers->dialect))->version());
    }

    /**
     * @param list<Node> $attributes
     * @return array{ColumnDefinition, list<TableConstraint>}
     */
    public function read(Node $node, array $attributes, ?Node $table = null): array
    {
        $nameNode = Tree::outer($node, $this->identifiers->dialect->platform()->syntax()->nodes('columnName'))[0] ?? null;
        $typeNode = Tree::outer($node, $this->identifiers->dialect->platform()->syntax()->nodes('declaredType'))[0] ?? null;
        if ($nameNode === null || $typeNode === null) {
            Tree::unsupported($node, 'column declaration');
        }
        $name = $this->identifiers->parts($nameNode)[0];
        $type = (new TypeReader($this->identifiers->dialect))->read($typeNode, $table);
        $nullability = Nullability::MaybeNull;
        $default = null;
        $constraints = [];
        foreach ($attributes as $attribute) {
            $constraint = (new ConstraintReader($this->identifiers, $this->values))->read($attribute, $name);
            if ($constraint !== null) {
                $constraints[] = $constraint;
                continue;
            }
            $tokens = $attribute->tokens();
            if (strtoupper($tokens[0]->text ?? '') === 'CONSTRAINT') {
                $tokens = array_slice($tokens, 2);
            }
            $text = strtoupper(implode(' ', array_map(static fn ($token): string => $token->text, $tokens)));
            if (preg_match('/^NOT NULL(?: |$)/', $text) === 1) {
                $nullability = Nullability::NotNull;
            } elseif (str_starts_with($text, 'DEFAULT ')) {
                $default = $this->values->read($attribute);
            }
        }

        $properties = new ColumnProperties($this->identifiers, $this->values);
        $generation = $properties->generation($node, $attributes);
        if ($generation?->kind === \SqlSemantics\Core\Schema\GenerationKind::Identity || $properties->autoIncrement($attributes)) {
            $nullability = Nullability::NotNull;
        }
        return [new ColumnDefinition($name, $type, $nullability, $this->values->read($node), $default, $generation, $properties->collation($attributes), $properties->autoIncrement($attributes), array_map($this->values->read(...), $attributes)), $constraints];
    }
}
