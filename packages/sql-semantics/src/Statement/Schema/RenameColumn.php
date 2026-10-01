<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Schema;

use SqlSemantics\Statement\Expression\ColumnReference;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Relation\Scope;
use SqlSemantics\Statement\Relation\TableReference;

/**
 * Requests a new column name and retains the original column declaration identity.
 * @example Describing a column rename
 *     $catalog = new \SqlSemantics\Statement\Schema\Catalog(new \SqlSemantics\Statement\Schema\SearchPath(new \SqlSemantics\Statement\Identifier\Name('main')), complete: false);
 *     $table = new \SqlSemantics\Statement\Relation\TableReference($catalog, new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('users')));
 *     (new \SqlSemantics\Statement\Schema\RenameColumn($table, new \SqlSemantics\Statement\Identifier\Name('id'), new \SqlSemantics\Statement\Identifier\Name('key')))->toString() // => 'ALTER TABLE users RENAME COLUMN id TO key'
 * @visibility public
 */
final class RenameColumn implements Operation
{
    /**
     * The original column reference, resolved against the unchanged declaration.
     */
    public readonly ColumnReference $column;

    /**
     * Describes the requested operation without applying it to the declaration context.
     */
    public function __construct(public readonly TableReference $table, Name $name, public readonly Name $newName, public readonly bool $explicitColumn = true)
    {
        assert($table->alias === null, 'A schema operation target cannot have a query alias.');
        $this->column = new ColumnReference(new Scope($table->catalog, $table), $name);
    }

    /**
     * Reconstructs SQL from the operation target and semantic options.
     */
    public function toString(): string
    {
        return 'ALTER TABLE ' . $this->table->name->toString() . ' RENAME ' . ($this->explicitColumn ? 'COLUMN ' : '') . $this->column->name->toString() . ' TO ' . $this->newName->toString();
    }
}
