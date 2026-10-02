<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Schema;

use SqlSemantics\Statement\Expression\ColumnReference;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Relation\TableReference;

/**
 * Requests removal of a column without modifying the supplied table definition.
 * @example Describing a column removal
 *     $catalog = new \SqlSemantics\Statement\Schema\Catalog(new \SqlSemantics\Statement\Schema\SearchPath(new \SqlSemantics\Statement\Identifier\Name('main')), complete: false);
 *     $table = new \SqlSemantics\Statement\Relation\TableReference($catalog, new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('users')));
 *     (new \SqlSemantics\Statement\Schema\DropColumn($table, new \SqlSemantics\Statement\Identifier\Name('id')))->toString() // => 'ALTER TABLE users DROP COLUMN id'
 * @visibility public
 */
final class DropColumn implements Operation
{
    use \SqlSemantics\Statement\Validation\Snapshot;

    /**
     * The original column reference, resolved against the unchanged declaration.
     */
    public readonly ColumnReference $column;

    /**
     * Describes the requested operation without applying it to the declaration context.
     */
    public function __construct(public readonly TableReference $table, Name $name, public readonly bool $explicitColumn = true)
    {
        \SqlSemantics\Statement\Validation\Check::input($table->alias === null, 'A schema operation target cannot have a query alias.');
        $this->column = new ColumnReference(new Scope($table->catalog, $table), $name);
    }

    /**
     * Reconstructs SQL from the operation target and semantic options.
     */
    public function toString(): string
    {
        return 'ALTER TABLE ' . $this->table->name->toString() . ' DROP ' . ($this->explicitColumn ? 'COLUMN ' : '') . $this->column->name->toString();
    }
}
