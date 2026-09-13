<?php

declare(strict_types=1);

namespace ZtdQuery\Adapter\Pdo\Driver;

use PDO;
use PDOException;
use PDOStatement as NativePdoStatement;
use ZtdQuery\Connection\Exception\DatabaseException;
use ZtdQuery\Connection\ResultColumn;
use ZtdQuery\Connection\StatementInterface;
use ZtdQuery\Platform\ResultColumnTypeResolver;

/**
 * PDO statement implementing StatementInterface for ZTD layer.
 *
 * This class wraps a PDOStatement and provides the minimal interface
 * required by the ZTD session for executing statements and fetching results.
 *
 *
 * @visibility ZtdQuery\Adapter\Pdo
 */
final class PdoStatement implements StatementInterface
{
    private NativePdoStatement $statement;

    /**
     * Binds the instance to what it will work from.
     *
     * @param NativePdoStatement $statement
     */
    public function __construct(NativePdoStatement $statement)
    {
        $this->statement = $statement;
    }

    /**
     * {@inheritDoc}
     *
     * @throws DatabaseException On database error when PDO is in exception mode.
     */
    public function execute(?array $params = null): bool
    {
        try {
            return $this->statement->execute($params);
        } catch (PDOException $e) {
            throw new DatabaseException(
                $e->getMessage(),
                is_int($e->errorInfo[1] ?? null) ? $e->errorInfo[1] : null,
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * {@inheritDoc}
     *
     * @throws DatabaseException When the native driver returns an invalid row.
     */
    public function fetchAll(): array
    {
        $rows = [];
        while (($row = $this->statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            if (!is_array($row)) {
                throw new DatabaseException('PDO returned a non-array row for FETCH_ASSOC.');
            }
            $columns = [];
            foreach ($row as $name => $value) {
                $columns["{$name}"] = $value;
            }
            $rows[] = $columns;
        }

        return $rows;
    }

    /**
     * {@inheritDoc}
     */
    public function resultColumns(ResultColumnTypeResolver $typeResolver): array
    {
        $columns = [];
        for ($index = 0; $index < $this->statement->columnCount(); $index++) {
            $metadata = $this->statement->getColumnMeta($index);
            if (!is_array($metadata)) {
                continue;
            }

            $columns[] = new ResultColumn(
                $metadata['name'],
                $typeResolver->resolve($metadata),
            );
        }

        return $columns;
    }

    /**
     * {@inheritDoc}
     */
    public function rowCount(): int
    {
        return $this->statement->rowCount();
    }
}
