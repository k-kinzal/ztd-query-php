<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Ast;

use SqlParser\Parser\Node;
use SqlSemantics\Core\Analysis\ValueReader;
use SqlSemantics\Core\Language;
use SqlSemantics\Core\SemanticException;
use SqlSemantics\Statement\Declaration\ColumnDefinition;
use SqlSemantics\Statement\Declaration\ConstraintKind;
use SqlSemantics\Statement\Declaration\Nullability;
use SqlSemantics\Statement\Declaration\TableConstraint;
use SqlSemantics\Statement\Declaration\TableDefinition;

/**
 * Reads a table declaration from a CREATE TABLE syntax node.
 *
 * @visibility SqlSemantics
 */
final class SchemaReader
{
    private readonly ValueReader $values;
    /**
     * Binds the dependencies used for semantic binding.
     */
    public function __construct(public readonly Identifiers $identifiers, public readonly string $defaultSchema, ?ValueReader $values = null)
    {
        $this->values = $values ?? $identifiers->dialect->platform()->values((new Language($identifiers->dialect))->version);
    }

    /**
     * Reads one table and promotes the nullability of primary key columns.
     *
     * @throws SemanticException When the declaration uses unsupported syntax or is invalid
     */
    public function table(Node $create): TableDefinition
    {
        $header = Tree::outer($create, $this->identifiers->dialect->platform()->syntax()->nodes('createHeader'))[0] ?? $create;
        $nameNode = Tree::child($header, $this->identifiers->dialect->platform()->syntax()->nodes('tableName'));
        if ($nameNode === null) {
            Tree::unsupported($create, 'table name');
        }
        $parts = $this->identifiers->parts($nameNode);
        $parts = $this->identifiers->dialect->platform()->schema()->qualify($header, $parts, $this->identifiers);
        if (count($parts) > 2) {
            Tree::unsupported($nameNode, 'three-part table name');
        }
        $this->validate($create, $header);
        $columns = [];
        $declarations = [];
        $constraints = [];
        foreach ($this->columnNodes($create) as [$column, $attributes]) {
            [$definition, $localConstraints] = (new ColumnReader($this->identifiers, $this->values))->read($column, $attributes, $create);
            $columns[] = $definition;
            $declarations[] = $column;
            array_push($constraints, ...$localConstraints);
        }
        foreach (Tree::outer($create, $this->identifiers->dialect->platform()->syntax()->nodes('tableConstraint')) as $node) {
            $constraint = (new ConstraintReader($this->identifiers, $this->values))->read($node);
            if ($constraint !== null) {
                $constraints[] = $constraint;
            }
        }
        if ($columns === [] && Tree::outer($create, $this->identifiers->dialect->platform()->syntax()->nodes('tableElements')) === []) {
            Tree::unsupported($create, 'CREATE TABLE without column declarations');
        }
        $columns = $this->primaryKeys($columns, $declarations, $constraints, $create);

        return new TableDefinition(count($parts) === 2 ? $parts[0] : $this->defaultSchema, $parts[count($parts) - 1], $columns, $constraints, $this->values->read($create), array_map($this->values->read(...), $this->identifiers->dialect->platform()->schema()->options($create)));
    }

    /**
     * Rejects declarations whose column state requires evaluating another relation.
     */
    public function validate(Node $source, Node $header): void
    {
        $this->identifiers->dialect->platform()->schema()->validate($source, $header);
    }

    /**
     * @return list<array{Node, list<Node>}>
     */
    public function columnNodes(Node $create): array
    {
        return $this->identifiers->dialect->platform()->schema()->columnNodes($create);
    }

    /**
     * @param list<ColumnDefinition> $columns
     * @param list<Node> $declarations Column declaration syntax, parallel to the columns
     * @param list<TableConstraint> $constraints
     * @return list<ColumnDefinition>
     * @throws SemanticException
     */
    public function primaryKeys(array $columns, array $declarations, array $constraints, Node $source): array
    {
        $names = array_map(fn (ColumnDefinition $column): string => $this->identifiers->dialect->platform()->names()->key($column->name), $columns);
        if (count(array_unique($names)) !== count($names)) {
            throw new SemanticException('duplicate-column', 'Duplicate column declaration.', $source);
        }
        $primary = [];
        foreach ($constraints as $constraint) {
            foreach ($constraint->columns as $name) {
                if (!in_array($this->identifiers->dialect->platform()->names()->key($name), $names, true)) {
                    throw new SemanticException('unknown-column', 'Constraint references unknown column: ' . $name, $constraint->source);
                }
            }
            if ($constraint->kind === ConstraintKind::PrimaryKey) {
                if ($primary !== []) {
                    throw new SemanticException('duplicate-primary-key', 'More than one primary key.', $constraint->source);
                }
                $primary = array_map(fn (string $name): string => $this->identifiers->dialect->platform()->names()->key($name), $constraint->columns);
            }
        }
        $result = [];
        foreach ($columns as $index => $column) {
            $notNull = in_array($this->identifiers->dialect->platform()->names()->key($column->name), $primary, true) && ($this->identifiers->dialect->platform()->schema()->primaryOptionsNotNull($source) || $this->primaryNotNull($column, $declarations[$index], $primary, $constraints));
            $result[] = $column->withNullability($notNull ? Nullability::NotNull : $column->nullability);
        }

        return $result;
    }

    /**
     * @param Node $declaration Original column declaration, for rules that depend on its exact spelling
     * @param list<string> $primary
     * @param list<TableConstraint> $constraints
     */
    public function primaryNotNull(ColumnDefinition $column, Node $declaration, array $primary, array $constraints): bool
    {
        return $this->identifiers->dialect->platform()->schema()->primaryNotNull($column, $declaration, $primary, $constraints);
    }
}
