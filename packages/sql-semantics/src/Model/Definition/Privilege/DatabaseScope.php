<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Privilege;

use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * All objects of one named database: the db.* privilege level.
 * @visibility public
 * @example Reading the database of a privilege level
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('GRANT SELECT ON app.* TO u');
 *     $statement->target->database // => 'app'
 */
final class DatabaseScope
{
    /**
     * Requires a nonempty database name.
     * @throws InvalidStructure
     */
    public function __construct(public readonly string $database)
    {
        if ($database === '') {
            throw new InvalidStructure('A database privilege level requires a nonempty database name.');
        }
    }
}
