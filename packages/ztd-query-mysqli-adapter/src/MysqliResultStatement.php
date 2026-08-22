<?php

declare(strict_types=1);

namespace ZtdQuery\Adapter\Mysqli;

use mysqli_result;
use ZtdQuery\Connection\StatementInterface;
use ZtdQuery\Platform\ResultColumnTypeResolver;

/**
 * mysqli result adapter implementing StatementInterface for ZTD layer.
 *
 * This class wraps a mysqli_result from query() and provides the minimal interface
 * required by the ZTD session for fetching results.
 */
final class MysqliResultStatement implements StatementInterface
{
    private ?mysqli_result $result;

    private int $affectedRows;

    public function __construct(?mysqli_result $result, int|string $affectedRows)
    {
        $this->result = $result;
        $normalizedAffectedRows = filter_var($affectedRows, FILTER_VALIDATE_INT);
        $this->affectedRows = $normalizedAffectedRows === false ? PHP_INT_MAX : $normalizedAffectedRows;
    }

    /**
     * {@inheritDoc}
     *
     * This is a no-op for result statements from query() as they're already executed.
     */
    public function execute(?array $params = null): bool
    {
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function fetchAll(): array
    {
        if ($this->result === null) {
            return [];
        }

        /** @var array<int, array<string, mixed>> $rows */
        $rows = $this->result->fetch_all(MYSQLI_ASSOC);

        return $rows;
    }

    /**
     * {@inheritDoc}
     */
    public function resultColumns(?ResultColumnTypeResolver $typeResolver = null): array
    {
        if ($this->result === null) {
            return [];
        }

        return MysqliResultColumnExtractor::extract($this->result, $typeResolver);
    }

    /**
     * {@inheritDoc}
     */
    public function rowCount(): int
    {
        return $this->affectedRows;
    }
}
