<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Privilege\PostgreSql;

use SqlSemantics\Model\Validation\Collections;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * SELECT, INSERT, UPDATE, REFERENCES, or ALL PRIVILEGES restricted to named columns of tables.
 * @visibility public
 * @example Reading a column privilege
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER, b INTEGER)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('GRANT SELECT (a, b) ON t TO alice');
 *     [$statement->privileges[0]->privilege->value, $statement->privileges[0]->columns] // => ['SELECT', ['a', 'b']]
 * @example Rejecting a privilege without a column form
 *     new \SqlSemantics\Model\Definition\Privilege\PostgreSql\ColumnPrivilege(\SqlSemantics\Model\Definition\Privilege\PostgreSql\Privilege::Delete, ['a']); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class ColumnPrivilege
{
    /**
     * @param non-empty-list<string> $columns Column names in request order
     * @throws InvalidStructure
     */
    public function __construct(public readonly Privilege $privilege, public readonly array $columns)
    {
        if (!$privilege->columnar()) {
            throw new InvalidStructure('Only SELECT, INSERT, UPDATE, REFERENCES, and ALL PRIVILEGES accept a column list.');
        }
        Collections::strings(Collections::nonEmpty($columns));
        if (in_array('', $columns, true)) {
            throw new InvalidStructure('A column privilege requires nonempty column names.');
        }
    }
}
