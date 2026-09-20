<?php

declare(strict_types=1);

namespace Fuzz\Shared\Oracle;

use Fuzz\Shared\Database\Sandbox;
use Fuzz\Shared\Database\SessionExecutor;
use PDOException;

/**
 * Checks row state and schema even when no query returned any data.
 */
final class State
{
    /**
     * Compare every virtual table and column with the independent database.
     * @throws Finding
     * @throws PDOException
     * @throws \ZtdQuery\Connection\Exception\DatabaseException
     */
    public static function verify(Sandbox $native, SessionExecutor $ztd): void
    {
        $tables = $native->catalog->tables();
        foreach ($tables as $table) {
            $definition = $ztd->session->tableDefinition($table);
            if ($definition === null) {
                throw new Finding('Native table is missing from the virtual schema: ' . $table);
            }
            $sql = 'SELECT * FROM ' . $native->catalog->quote($table) . ' ORDER BY id';
            Comparison::same(Comparison::rows($native->catalog->query($sql)), Comparison::rows($ztd->rows($sql)), 'Table state differs: ' . $table);
            $statement = $native->pdo->query($sql . ' LIMIT 0');
            if ($statement === false) {
                throw new Finding('Native schema query silently failed.');
            }
            $columns = [];
            for ($index = 0; $index < $statement->columnCount(); ++$index) {
                $metadata = $statement->getColumnMeta($index);
                if ($metadata === false) {
                    throw new Finding('Native schema metadata unavailable.');
                }
                $columns[] = $metadata['name'];
            }
            Comparison::same($columns, $definition->columns, 'Virtual columns differ: ' . $table);
        }
        if (!in_array('scratch', $tables, true)) {
            Comparison::same(null, $ztd->session->tableDefinition('scratch'), 'Dropped table remains in virtual schema.');
        }
    }
}
