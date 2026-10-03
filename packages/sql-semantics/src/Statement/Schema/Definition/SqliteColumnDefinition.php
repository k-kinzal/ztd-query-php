<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Schema\Definition;

use SqlSemantics\Statement\Declaration\Nullability;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Schema\Column;
use SqlSemantics\Statement\SemanticGraph;
use SqlSemantics\Statement\Type\SqliteDeclaration;

/**
 * A column's type and content rules, with one shared declaration identity for query references.
 * @visibility public
 * @example Declaring a column and retrieving its known type
 *     $definition = new \SqlSemantics\Statement\Schema\Definition\SqliteColumnDefinition(new \SqlSemantics\Statement\Identifier\Name('id'), new \SqlSemantics\Statement\Type\SqliteDeclaration('INTEGER'));
 *     $definition->column->type->label() // => 'integer'
 */
final class SqliteColumnDefinition
{
    /**
     * @var list<ColumnConstraint>
     */
    public readonly array $constraints;

    /**
     * The declaration object that references retain by identity.
     */
    public readonly Column $column;

    /**
     * Derives column facts from its type and constraints instead of accepting contradictory facts.
     */
    public function __construct(Name $name, public readonly SqliteDeclaration $type, ColumnConstraint ...$constraints)
    {
        $this->constraints = array_values($constraints);
        $notNull = false;
        foreach ($constraints as $constraint) {
            assert((new SemanticGraph())->containsOnlyValues($constraint), 'Column rules must be immutable semantic values.');
            $notNull = $notNull || ($constraint instanceof ColumnNullability && !$constraint->allowsNull)
                || ($constraint instanceof ColumnPrimaryKey && ($type->strict || ($type->permitsRowidAlias() && $constraint->direction !== KeyDirection::Descending)));
        }
        $this->column = new Column($name, $type->descriptor, $notNull ? Nullability::NotNull : Nullability::MaybeNull);
    }

    /**
     * Reconstructs the column from its declaration and typed content rules.
     */
    public function toString(): string
    {
        $sql = $this->column->name->toString() . ($this->type->name === null ? '' : ' ' . $this->type->toString());
        return $sql . ($this->constraints === [] ? '' : ' ' . implode(' ', array_map(static fn (ColumnConstraint $constraint): string => $constraint->toString(), $this->constraints)));
    }
}
