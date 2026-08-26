<?php

declare(strict_types=1);

namespace Tests\Fixture;

use PhpMyAdmin\SqlParser\Components\PartitionDefinition;
use PhpMyAdmin\SqlParser\Parser;
use PhpMyAdmin\SqlParser\Statements\CreateStatement;
use RuntimeException;

/**
 * The partitions a partitioning test works from.
 */
final class MySqlPartitions
{
    /**
     * Answers one partition, as MySQL would have declared it.
     *
     * @param string $name Name the partition is declared under
     * @param string $type How the table divides, LESS THAN or IN
     * @param string $values Values the partition is declared to hold, parentheses and all
     *
     * @return PartitionDefinition The partition, as the parser reads it
     *
     * @throws RuntimeException When the parser will not read what is written here
     */
    public static function declared(string $name, string $type, string $values): PartitionDefinition
    {
        $sql = 'CREATE TABLE t (id INT) PARTITION BY RANGE (id) '
            . "(PARTITION {$name} VALUES {$type} {$values})";
        $statement = (new Parser($sql))->statements[0] ?? null;
        $partition = $statement instanceof CreateStatement ? ($statement->partitions[0] ?? null) : null;
        if (!$partition instanceof PartitionDefinition) {
            throw new RuntimeException("Not a partition declaration: {$sql}");
        }

        return $partition;
    }
}
