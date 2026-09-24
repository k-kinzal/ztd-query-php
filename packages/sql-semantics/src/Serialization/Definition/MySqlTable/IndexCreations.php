<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition\MySqlTable;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\IndexAlgorithm;
use SqlSemantics\Model\Definition\IndexLock;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\CreateIndexStatement;
use SqlSemantics\Schema\IndexDefinition;
use SqlSemantics\Serialization\Definition\Indexes;

/**
 * Writes MySQL CREATE INDEX with the index type after the key list and the rebuild policies last.
 * @visibility SqlSemantics
 */
final class IndexCreations
{
    /**
     * Writes the statement from its operands.
     * @param list<Tree> $name The written index name
     */
    public static function write(CreateIndexStatement $statement, array $name, Tree $target): Tree
    {
        $definition = $statement->index->definition;
        return new Tree('create-index', [Build::keyword('CREATE ' . Indexes::kind($definition) . 'INDEX'), ...$name, Build::keyword('ON'), $target, Indexes::keys($definition, Dialect::MySql), self::method($definition), Indexes::options($definition->properties, Dialect::MySql), ...self::policies($statement->algorithm, $statement->lock)]);
    }

    /**
     * Writes USING BTREE, HASH, or RTREE.
     */
    public static function method(IndexDefinition $definition): Tree
    {
        return $definition->method === null ? new Tree('index-method', []) : Build::keyword('USING ' . strtoupper($definition->method));
    }

    /**
     * Writes nondefault ALGORITHM and LOCK requests.
     * @return list<Tree>
     */
    public static function policies(IndexAlgorithm $algorithm, IndexLock $lock): array
    {
        return [...($algorithm === IndexAlgorithm::Default ? [] : [Build::keyword('ALGORITHM = ' . $algorithm->value)]), ...($lock === IndexLock::Default ? [] : [Build::keyword('LOCK = ' . $lock->value)])];
    }
}
