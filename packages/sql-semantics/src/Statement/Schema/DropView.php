<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Schema;

use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Relation\TableReference;

/**
 * Removes a named view definition, optionally requiring it to exist.
 * @example Describing a conditional removal
 *     $catalog = new \SqlSemantics\Statement\Schema\Catalog(new \SqlSemantics\Statement\Schema\SearchPath(new \SqlSemantics\Statement\Identifier\Name('main')), complete: false);
 *     $table = new \SqlSemantics\Statement\Relation\TableReference($catalog, new \SqlSemantics\Statement\Identifier\QualifiedName(new \SqlSemantics\Statement\Identifier\Name('users')));
 *     (new \SqlSemantics\Statement\Schema\DropView($table, true))->toString() // => 'DROP VIEW IF EXISTS users'
 * @visibility public
 */
final class DropView implements Operation
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
        return 'DROP VIEW ' . ($this->ifExists ? 'IF EXISTS ' : '') . $this->table->name->toString();
    }
}
