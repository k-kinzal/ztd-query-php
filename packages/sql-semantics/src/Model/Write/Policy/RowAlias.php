<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Write\Policy;

use SqlSemantics\Model\Relation\ProposedRow;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * MySQL 8.0.19+ `AS alias [(columns)]` after VALUES or SET: names the proposed row that ON DUPLICATE KEY UPDATE can read.
 * The proposed row has one column per inserted column, in insertion order, named by the column aliases when they are given.
 * @visibility public
 * @example Reading the proposed row alias and its column aliases
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t (id INT PRIMARY KEY, a INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('INSERT INTO t (id, a) VALUES (1, 2) AS n(i, x) ON DUPLICATE KEY UPDATE a = x');
 *     $statement->policy->rowAlias->row->alias // => 'n'
 *     $statement->policy->rowAlias->columns // => ['i', 'x']
 *     (new \SqlSemantics\SimpleSerializer())->serialize($statement) // => 'INSERT INTO `t`(`id`, `a`) VALUES (1, 2) AS `n`(`i`, `x`) ON DUPLICATE KEY UPDATE `a` = `x`'
 */
final class RowAlias
{
    /**
     * @param ProposedRow $row The proposed row relation, named by the alias, whose columns follow the inserted columns
     * @param list<string> $columns The column aliases as written; empty when the proposed row keeps the inserted column names
     * @throws InvalidStructure
     */
    public function __construct(public readonly ProposedRow $row, public readonly array $columns = [])
    {
        if ($row->alias === null || $row->alias === '') {
            throw new InvalidStructure('An insert row alias names its proposed row.');
        }
        $names = array_map(static fn (\SqlSemantics\Schema\ColumnDefinition $column): string => $column->name, $row->declaration->columns);
        if ($columns !== [] && $columns !== $names) {
            throw new InvalidStructure('Insert row column aliases name the proposed row columns in insertion order.');
        }
        if (count(array_unique(array_map(strtolower(...), $names))) !== count($names)) {
            throw new InvalidStructure('Insert row column names are distinct.');
        }
    }
}
