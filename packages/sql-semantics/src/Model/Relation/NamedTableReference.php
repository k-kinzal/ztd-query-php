<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Relation;

use Override;

/**

 * A resolved or unresolved named table occurrence, sharing its declaration and qualification.
 * @visibility public
 * @example Reading the table identity
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('TABLE ONLY t');
 *     $statement->from->name->parts // => ['public', 't']
 *     $statement->from->sample // => null

 */
abstract class NamedTableReference extends \SqlSemantics\Model\TableUse
{
    /**
     * @param \SqlSemantics\Model\Query\Sampling\TableSample|null $sample TABLESAMPLE clause reading a sample of the rows
     * @visibility SqlSemantics
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(
        string $id,
        string $scopeId,
        \SqlSemantics\Schema\TableDefinition $declaration,
        public readonly QualifiedName $name,
        ?string $alias,
        \SqlParser\Parser\Node $source,
        public readonly ?\SqlSemantics\Model\Query\Sampling\TableSample $sample = null,
    ) {
        if (count($name->parts) > 2 || $name->parts[count($name->parts) - 1] !== $declaration->name || (count($name->parts) === 2 && $name->parts[0] !== $declaration->schema)) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('A table reference name must identify its declaration.');
        }
        parent::__construct($id, $scopeId, $declaration, $alias, $source);
    }

    /**
     * Returns the ordered value expressions exposed by this relation.
     * @return list<\SqlSemantics\Model\Expression>
     */
    #[Override]
    public function resultExpressions(): array
    {
        return [];
    }

}
