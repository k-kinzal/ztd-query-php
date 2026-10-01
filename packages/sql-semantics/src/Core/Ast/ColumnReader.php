<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Ast;

use SqlParser\Parser\Node;
use SqlSemantics\Core\Analysis\ValueReader;
use SqlSemantics\Core\Language;
use SqlSemantics\Statement\Declaration\ColumnDefinition;
use SqlSemantics\Statement\Declaration\ConstraintKind;
use SqlSemantics\Statement\Declaration\GenerationKind;
use SqlSemantics\Statement\Declaration\Nullability;
use SqlSemantics\Statement\Declaration\TableConstraint;

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
        $this->values = $values ?? $identifiers->dialect->platform()->values((new Language($identifiers->dialect))->version);
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
        $declared = (new TypeReader($this->identifiers->dialect))->read($typeNode, $this->values, $table);
        $rules = $this->identifiers->dialect->platform()->schema();
        $nullability = $rules->nullability($node, $attributes, $declared->notNull ? Nullability::NotNull : Nullability::MaybeNull);
        $default = null;
        $defaultValue = null;
        $constraints = [];
        if ($declared->unique) {
            $constraints[] = new TableConstraint(ConstraintKind::Unique, [$name], $this->values->read($typeNode), inline: true);
        }
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
            if (str_starts_with($text, 'DEFAULT ')) {
                $default = $this->values->read($attribute);
                $defaultValue = $rules->defaultValue($attribute, $this->values);
            } elseif ($text === 'SERIAL DEFAULT VALUE') {
                $constraints[] = new TableConstraint(ConstraintKind::Unique, [$name], $this->values->read($attribute), inline: true);
            }
        }

        $properties = new ColumnProperties($this->identifiers, $this->values);
        $generation = $properties->generation($node, $attributes);
        $autoIncrement = $declared->autoIncrement || $properties->autoIncrement($attributes);
        if ($generation?->kind === GenerationKind::Identity || $autoIncrement) {
            $nullability = Nullability::NotNull;
        }
        return [new ColumnDefinition($name, $declared->type, $nullability, $this->values->read($node), $default, $generation, $properties->collation($attributes), $autoIncrement, array_map($this->values->read(...), $attributes), $defaultValue), $constraints];
    }
}
