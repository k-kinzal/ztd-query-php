<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Relation\Foreign;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Catalog\CatalogInvariant;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Schema\Column\Generation;
use SqlSemantics\Schema\TableConstraint;
use SqlSemantics\Type\Nullability;

/**
 * Overrides for one inherited column of a partition: the type comes from the parent, everything else is declared here.
 * @visibility public
 * @example Reading a partition column override
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE p(a INTEGER)')))->bind('CREATE FOREIGN TABLE ft PARTITION OF p (a WITH OPTIONS NOT NULL DEFAULT 1) DEFAULT SERVER s');
 *     $statement->columns[0]->column // => 'a'
 *     $statement->columns[0]->nullability // => \SqlSemantics\Type\Nullability::NotNull
 * @example Rejecting a nullability that a declaration cannot express
 *     new \SqlSemantics\Model\Definition\Relation\Foreign\PartitionColumn('a', \SqlSemantics\Type\Nullability::Unknown); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class PartitionColumn
{
    /**
     * @param list<TableConstraint> $constraints
     * @throws InvalidStructure
     */
    public function __construct(
        public readonly string $column,
        public readonly Nullability $nullability = Nullability::MaybeNull,
        public readonly Generation $generation = new \SqlSemantics\Schema\Column\SuppliedColumn(),
        public readonly ?QualifiedName $collation = null,
        public readonly array $constraints = [],
    ) {
        CatalogInvariant::identifier($column);
        if (!in_array($nullability, [Nullability::NotNull, Nullability::MaybeNull], true)) {
            throw new InvalidStructure('A column declaration is either NOT NULL or nullable.');
        }
        foreach ($generation->expressions() as $expression) {
            if ($expression->type->dialect !== Dialect::PostgreSql) {
                throw new InvalidStructure('A partition column expression requires the PostgreSQL dialect.');
            }
        }
        if ($collation !== null) {
            CatalogInvariant::name($collation, 2);
        }
        Collections::objects($constraints, TableConstraint::class);
    }
}
