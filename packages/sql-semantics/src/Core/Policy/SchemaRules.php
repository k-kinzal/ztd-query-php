<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Policy;

use SqlParser\Parser\Node;
use SqlSemantics\Core\Ast\Identifiers;
use SqlSemantics\Core\Schema\ColumnDefinition;
use SqlSemantics\Core\Schema\TableConstraint;
use SqlSemantics\Core\Schema\TableDefinition;

/**
 * Supplies declaration syntax and constraint behavior.
 *
 * @visibility SqlSemantics
 */
interface SchemaRules
{
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
     * @param list<string> $primary
     * @param list<TableConstraint> $constraints
     */
    public function primaryNotNull(ColumnDefinition $column, array $primary, array $constraints): bool;

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
