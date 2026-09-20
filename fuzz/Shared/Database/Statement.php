<?php

declare(strict_types=1);

namespace Fuzz\Shared\Database;

use Fuzz\Shared\Oracle\Finding;
use PDO;
use PDOException;
use PDOStatement;
use ZtdQuery\Connection\ResultColumn;
use ZtdQuery\Connection\StatementInterface;
use ZtdQuery\Platform\ResultColumnTypeResolver;

/**
 * Native driver bridge for platform testing, independent of both production adapters.
 */
final class Statement implements StatementInterface
{
    /**
     * Wrap the native result used by the platform session.
     */
    public function __construct(private readonly PDOStatement $statement)
    {
    }

    /**
     * Execute the native statement with the provided parameters.
     * @throws PDOException
     */
    public function execute(?array $params = null): bool
    {
        return $this->statement->execute($params);
    }

    /**
     * Read associative rows without changing their values.
     * @throws Finding
     * @throws PDOException
     */
    public function fetchAll(): array
    {
        $rows = [];
        foreach ($this->statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (!is_array($row)) {
                throw new Finding('Native driver did not return an associative row.');
            }
            $values = [];
            foreach ($row as $column => $value) {
                $values[(string) $column] = $value;
            }
            $rows[] = $values;
        }
        return $rows;
    }

    /**
     * Resolve native result metadata through the platform contract.
     * @throws Finding
     */
    public function resultColumns(ResultColumnTypeResolver $typeResolver): array
    {
        $columns = [];
        for ($index = 0; $index < $this->statement->columnCount(); ++$index) {
            $metadata = $this->statement->getColumnMeta($index);
            if ($metadata === false) {
                throw new Finding('Native driver omitted result metadata.');
            }
            $columns[] = new ResultColumn($metadata['name'], $typeResolver->resolve($metadata));
        }
        return $columns;
    }

    /**
     * Return the native affected-row count.
     */
    public function rowCount(): int
    {
        return $this->statement->rowCount();
    }
}
