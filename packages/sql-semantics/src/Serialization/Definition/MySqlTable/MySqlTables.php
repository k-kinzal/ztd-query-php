<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition\MySqlTable;

use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\IndexLock;
use SqlSemantics\Model\Definition\MySqlTable\Table;
use SqlSemantics\Model\Definition\MySqlTable\TableAlgorithm;
use SqlSemantics\Model\Definition\MySqlTable\TableAlteration;
use SqlSemantics\Model\Definition\MySqlTable\TableRenaming;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Definition\MySql\Table as Statement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Serialization\Expressions;
use SqlSemantics\Serialization\Query\Relations;

/**
 * Writes MySQL table renaming and alteration requests and the MySQL 5 parser entries from their typed operands.
 * @visibility SqlSemantics
 */
final class MySqlTables
{
    /**
     * Returns null for statements outside MySQL table definition.
     */
    public static function write(BoundStatement $statement): ?Tree
    {
        return match (true) {
            $statement instanceof Statement\RenameTablesStatement => new Tree('rename-tables', [Build::keyword('RENAME TABLE'), Build::separated(array_map(self::renaming(...), $statement->renamings))]),
            $statement instanceof Statement\AlterTableStatement => self::alter($statement),
            $statement instanceof Statement\PartitionSchemeStatement => Partitionings::write($statement->partitioning),
            $statement instanceof Statement\GeneratedColumnExpressionStatement => new Tree('parse-gcol-expr', [Build::keyword('PARSE_GCOL_EXPR'), Build::parentheses(Expressions::write($statement->expression))]),
            default => null,
        };
    }

    /**
     * Writes one source and target pair.
     */
    public static function renaming(TableRenaming $renaming): Tree
    {
        return new Tree('table-renaming', [Relations::target($renaming->table, Dialect::MySql), Build::keyword('TO'), Build::identifier($renaming->newName->parts, Dialect::MySql)]);
    }

    /**
     * Writes the requests first, then the alterations, with a trailing partitioning change separated by a space.
     */
    public static function alter(Statement\AlterTableStatement $statement): Tree
    {
        $items = [
            ...($statement->algorithm === TableAlgorithm::Default ? [] : [Build::keyword('ALGORITHM = ' . $statement->algorithm->value)]),
            ...($statement->lock === IndexLock::Default ? [] : [Build::keyword('LOCK = ' . $statement->lock->value)]),
            ...($statement->validation === null ? [] : [Build::keyword($statement->validation->value)]),
        ];
        $trailing = [];
        foreach ($statement->alterations as $alteration) {
            $written = self::alteration($alteration);
            if ($alteration instanceof Table\RepartitionTable || $alteration === Table\TableCommand::RemovePartitioning) {
                $trailing[] = $written;
                continue;
            }
            $items[] = $written;
        }
        return new Tree('alter-table', [Build::keyword('ALTER ' . ($statement->ignore ? 'IGNORE ' : '') . 'TABLE'), Relations::target($statement->table, Dialect::MySql), ...($items === [] ? [] : [Build::separated($items)]), ...$trailing]);
    }

    /**
     * Writes one alteration.
     * @throws InvalidStructure
     */
    public static function alteration(TableAlteration $alteration): Tree
    {
        return ColumnChanges::write($alteration) ?? TableChanges::write($alteration) ?? throw new InvalidStructure('Unclassified MySQL table alteration.');
    }
}
