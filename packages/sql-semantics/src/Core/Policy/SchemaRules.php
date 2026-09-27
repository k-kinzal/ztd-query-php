<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Policy;

use SqlParser\Parser\Node;
use SqlSemantics\Core\Analysis\ValueReader;
use SqlSemantics\Core\Ast\Identifiers;
use SqlSemantics\Statement\Declaration\ColumnDefinition;
use SqlSemantics\Statement\Declaration\Nullability;
use SqlSemantics\Statement\Declaration\TableConstraint;
use SqlSemantics\Statement\Declaration\TableDefinition;
use SqlSemantics\Statement\Element;

/**
 * Supplies declaration syntax and constraint behavior.
 *
 * @visibility SqlSemantics
 */
interface SchemaRules
{
    /**
     * Applies explicit NULL attributes under the dialect's conflict rules.
     * @param list<Node> $attributes
     */
    public function nullability(Node $column, array $attributes, Nullability $implicit): Nullability;

    /**
     * Reads the value of a DEFAULT clause independently of the clause itself.
     */
    public function defaultValue(Node $attribute, ValueReader $values): Element;

    /**
     * Implicit namespaces searched before the session path for declared tables.
     * @return list<string>
     */
    public function implicitSchemas(): array;

    /**
     * Reads key columns without interpreting expression operands as column names.
     * @return list<string>
     */
    public function keyColumns(Node $constraint, Identifiers $identifiers): array;

    /**
     * Rejects declarations whose column state requires evaluating another relation.
     */
    public function validate(Node $source, Node $header): void;

    /**
     * @return list<Node> Table options whose structure remains available to consumers
     */
    public function options(Node $source): array;

    /**
     * Reports whether table options make every primary key column nonnullable.
     */
    public function primaryOptionsNotNull(Node $source): bool;

    /**
     * @return list<array{Node, list<Node>}>
     */
    public function columnNodes(Node $create): array;

    /**
     * Reports whether a primary key column is nonnullable, given its exact declaration.
     *
     * @param list<string> $primary
     * @param list<TableConstraint> $constraints
     */
    public function primaryNotNull(ColumnDefinition $column, Node $declaration, array $primary, array $constraints): bool;

    /**
     * Selects the syntax node that owns the complete declaration.
     */
    public function schemaNode(Node $statement, Node $create): Node;

    /**
     * Returns the declaration identity used for duplicate detection.
     */
    public function tableKey(TableDefinition $table): string;

    /**
     * @param list<string> $parts
     * @return list<string>
     */
    public function qualify(Node $header, array $parts, Identifiers $identifiers): array;
}
