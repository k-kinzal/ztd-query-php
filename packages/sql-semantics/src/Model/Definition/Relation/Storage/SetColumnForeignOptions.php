<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Relation\Storage;

use SqlSemantics\Model\Definition\Catalog\CatalogInvariant;
use SqlSemantics\Model\Definition\Foreign\AddForeignOption;
use SqlSemantics\Model\Definition\Foreign\DropForeignOption;
use SqlSemantics\Model\Definition\Foreign\SetForeignOption;
use SqlSemantics\Model\Definition\RelationAction;
use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Adds, replaces, or removes foreign-data wrapper options of one column of a foreign table.
 * @visibility public
 * @example Reading a column option change
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE ft(c TEXT)')))->bind("ALTER FOREIGN TABLE ft ALTER COLUMN c OPTIONS (SET column_name 'remote_c')");
 *     $statement->actions[0]->column // => 'c'
 *     $statement->actions[0]->changes[0]->option->name // => 'column_name'
 */
final class SetColumnForeignOptions implements RelationAction
{
    /**
     * @param non-empty-list<AddForeignOption|SetForeignOption|DropForeignOption> $changes
     * @throws InvalidStructure
     */
    public function __construct(public readonly string $column, public readonly array $changes)
    {
        CatalogInvariant::identifier($column);
        Collections::alternatives(Collections::nonEmpty($changes), [AddForeignOption::class, SetForeignOption::class, DropForeignOption::class]);
    }
}
