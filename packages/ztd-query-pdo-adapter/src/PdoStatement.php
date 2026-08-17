<?php

declare(strict_types=1);

namespace ZtdQuery\Adapter\Pdo;

use PDO;
use PDOException;
use PDOStatement as NativePdoStatement;
use ZtdQuery\Connection\Exception\DatabaseException;
use ZtdQuery\Connection\ResultColumn;
use ZtdQuery\Connection\StatementInterface;
use ZtdQuery\Schema\ColumnType;

/**
 * PDO statement implementing StatementInterface for ZTD layer.
 *
 * This class wraps a PDOStatement and provides the minimal interface
 * required by the ZTD session for executing statements and fetching results.
 */
final class PdoStatement implements StatementInterface
{
    private NativePdoStatement $statement;

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
     */
    public function fetchAll(): array
    {
        /** @var array<int, array<string, mixed>> $rows */
        $rows = $this->statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows;
    }

    /**
     * {@inheritDoc}
     */
    public function resultColumns(): array
    {
        $columns = [];
        for ($index = 0; $index < $this->statement->columnCount(); $index++) {
            $metadata = $this->statement->getColumnMeta($index);
            if (!is_array($metadata)) {
                continue;
            }

            $columns[] = new ResultColumn(
                $metadata['name'],
                ColumnType::fromNativeType($this->nativeType($metadata)),
            );
        }

        return $columns;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function nativeType(array $metadata): string
    {
        $declaredType = array_key_exists('sqlite:decl_type', $metadata)
            ? $metadata['sqlite:decl_type']
            : null;
        if (is_string($declaredType)) {
            return $declaredType;
        }

        $nativeType = array_key_exists('native_type', $metadata) ? $metadata['native_type'] : '';
        if (!is_string($nativeType)) {
            return '';
        }

        $length = array_key_exists('len', $metadata) ? $metadata['len'] : null;
        if (is_int($length) && $length > 0 && !str_contains($nativeType, '(')) {
            $nativeType = match (strtoupper($nativeType)) {
                'VARCHAR' => "VARCHAR($length)",
                'BPCHAR' => "CHAR($length)",
                default => $nativeType,
            };
        }

        return $nativeType;
    }

    /**
     * {@inheritDoc}
     */
    public function rowCount(): int
    {
        return $this->statement->rowCount();
    }
}
