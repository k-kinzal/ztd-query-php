<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\MySqlTable\Column;

use SqlSemantics\Model\Definition\MySqlTable\AlterationInvariant;
use SqlSemantics\Model\Definition\MySqlTable\TableAlteration;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Replaces the default of a column by a literal, or from MySQL 8.0 by a parenthesized expression.
 * @visibility public
 * @example Setting a literal default
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('ALTER TABLE t ALTER COLUMN id SET DEFAULT 7');
 *     $statement->alterations[0]->default instanceof \SqlSemantics\Model\Scalar\Value\Literal // => true
 *     $statement->toString() // => 'ALTER TABLE `t` ALTER COLUMN `id` SET DEFAULT 7'
 * @example Setting an expression default
 *     $schema = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT)');
 *     $statement = (new \SqlSemantics\Binder($schema))->bind('ALTER TABLE t ALTER COLUMN id SET DEFAULT (id + 1)');
 *     $statement->alterations[0]->default instanceof \SqlSemantics\Model\Scalar\Operator\BinaryExpression // => true
 */
final class ColumnDefaultAssignment implements TableAlteration
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly string $column, public readonly Expression $default)
    {
        AlterationInvariant::name($column);
        AlterationInvariant::expression($default);
    }
}
