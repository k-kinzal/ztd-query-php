<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Schema;

use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Relation\TableReference;

/**
 * Requests a new table name while retaining the original declaration reference.
 * @example Describing a rename without changing the table declaration
 *     $catalog = new \SqlSemantics\Statement\Schema\Catalog(new \SqlSemantics\Statement\Schema\SearchPath(new \SqlSemantics\Statement\Identifier\Name('main')), complete: false);
 *     $table = new \SqlSemantics\Statement\Relation\TableReference($catalog, new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('users')));
 *     (new \SqlSemantics\Statement\Schema\RenameTable($table, new \SqlSemantics\Statement\Identifier\Name('people')))->toString() // => 'ALTER TABLE users RENAME TO people'
 * @visibility public
 */
final class RenameTable implements Operation
{
    use \SqlSemantics\Statement\Validation\Snapshot;

    /**
     * Describes the requested operation without applying it to the declaration context.
     */
    public function __construct(public readonly TableReference $table, public readonly Name $newName)
    {
        \SqlSemantics\Statement\Validation\Check::input($table->alias === null, 'A schema operation target cannot have a query alias.');
    }

    /**
     * Reconstructs SQL from the operation target and semantic options.
     */
    public function toString(): string
    {
        return 'ALTER TABLE ' . $this->table->name->toString() . ' RENAME TO ' . $this->newName->toString();
    }
}
