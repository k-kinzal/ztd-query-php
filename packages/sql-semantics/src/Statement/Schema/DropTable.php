<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Schema;

use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Relation\TableReference;

/**
 * Removes a named table definition, optionally requiring it to exist.
 * @example Describing a conditional removal
 *     $catalog = new \SqlSemantics\Statement\Schema\Catalog(new \SqlSemantics\Statement\Schema\SearchPath(new \SqlSemantics\Statement\Identifier\Name('main')), complete: false);
 *     $table = new \SqlSemantics\Statement\Relation\TableReference($catalog, new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('users')));
 *     (new \SqlSemantics\Statement\Schema\DropTable($table, true))->toString() // => 'DROP TABLE IF EXISTS users'
 * @visibility public
 */
final class DropTable implements Operation
{
    /**
     * Describes the requested operation without applying it to the declaration context.
     */
    public function __construct(public readonly TableReference $table, public readonly bool $ifExists = false)
    {
        assert($table->alias === null, 'A schema operation target cannot have a query alias.');
    }

    /**
     * Reconstructs SQL from the operation target and semantic options.
     */
    public function toString(): string
    {
        return 'DROP TABLE ' . ($this->ifExists ? 'IF EXISTS ' : '') . $this->table->name->toString();
    }
}
