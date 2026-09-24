<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Privilege;

use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * SELECT, INSERT, UPDATE, or REFERENCES restricted to named columns of one table.
 * @visibility public
 * @example Reading a MySQL column privilege
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(a INT, b INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('GRANT SELECT (a, b) ON t TO u');
 *     [$statement->privileges[0]->privilege->value, $statement->privileges[0]->columns] // => ['SELECT', ['a', 'b']]
 * @example Rejecting a privilege without column scope
 *     new \SqlSemantics\Model\Definition\Privilege\ColumnPrivilege(\SqlSemantics\Model\Definition\Privilege\StaticPrivilege::Delete, ['a']); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class ColumnPrivilege
{
    /**
     * @param non-empty-list<string> $columns Column names in request order
     * @throws InvalidStructure
     */
    public function __construct(public readonly StaticPrivilege $privilege, public readonly array $columns)
    {
        if (!in_array($privilege, [StaticPrivilege::Select, StaticPrivilege::Insert, StaticPrivilege::Update, StaticPrivilege::References], true)) {
            throw new InvalidStructure('Only SELECT, INSERT, UPDATE, and REFERENCES accept a column list.');
        }
        Collections::strings(Collections::nonEmpty($columns));
    }
}
