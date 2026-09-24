<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Privilege;

use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * A MySQL 8 dynamic privilege named by its identifier, such as BACKUP_ADMIN; always global.
 * @visibility public
 * @example Reading a dynamic privilege name
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind('GRANT BACKUP_ADMIN ON *.* TO u');
 *     $statement->privileges[0]->name // => 'BACKUP_ADMIN'
 */
final class DynamicPrivilege
{
    /**
     * Requires a nonempty privilege name; the privilege is not resolved against the server.
     * @throws InvalidStructure
     */
    public function __construct(public readonly string $name)
    {
        if ($name === '') {
            throw new InvalidStructure('A dynamic privilege requires a nonempty name.');
        }
    }
}
