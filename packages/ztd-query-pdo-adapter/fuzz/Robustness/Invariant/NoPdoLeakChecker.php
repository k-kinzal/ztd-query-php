<?php

declare(strict_types=1);

namespace Fuzz\Robustness\Invariant;

use PDOException;
use ZtdQuery\Adapter\Pdo\ZtdPdoException;
use ZtdQuery\Connection\Exception\DatabaseException;
use ZtdQuery\Exception\ColumnAlreadyExistsException;
use ZtdQuery\Exception\ColumnNotFoundException;
use ZtdQuery\Exception\DuplicateKeyException;
use ZtdQuery\Exception\ForeignKeyViolationException;
use ZtdQuery\Exception\NotNullViolationException;
use ZtdQuery\Exception\SchemaNotFoundException;
use ZtdQuery\Exception\SqlParseException;
use ZtdQuery\Exception\TableAlreadyExistsException;
use ZtdQuery\Exception\UnknownSchemaException;
use ZtdQuery\Exception\UnsupportedSqlException;

final class NoPdoLeakChecker
{
    private const ALLOWED_RAW_PDO_ERRORS = [
        ['42S22', 1054], // Unknown column
        ['42S02', 1146], // Table doesn't exist
        ['42S02', 1109], // Unknown table in multi-table statement
        ['42000', 1327], // Undeclared variable
    ];

    /** @var callable(string): void */
    private $executor;

    /**
     * @param callable(string): void $executor Callable that executes SQL via ZtdPdo
     */
    public function __construct(callable $executor)
    {
        $this->executor = $executor;
    }

    public function check(string $sql): ?InvariantViolation
    {
        try {
            ($this->executor)($sql);
            return null;
        } catch (DatabaseException
               | UnsupportedSqlException
               | UnknownSchemaException
               | SchemaNotFoundException
               | ColumnNotFoundException
               | TableAlreadyExistsException
               | ColumnAlreadyExistsException
               | DuplicateKeyException
               | ForeignKeyViolationException
               | NotNullViolationException
               | SqlParseException) {
                   return null;
               } catch (ZtdPdoException) {
                   return null;
               } catch (PDOException $e) {
                   if ($this->isAllowedRawPdoException($e)) {
                       return null;
                   }

                   return new InvariantViolation(
                       'INV-L5-01',
                       'Unexpected raw PDOException escaped ZTD layer',
                       $sql,
                       [
                           'exception_class' => get_class($e),
                           'sqlstate' => is_scalar($e->errorInfo[0] ?? '') ? (string) ($e->errorInfo[0] ?? '') : '',
                           'driver_code' => is_int($e->errorInfo[1] ?? null) ? $e->errorInfo[1] : 0,
                           'exception_message' => $e->getMessage(),
                       ]
                   );
               }
    }

    private function isAllowedRawPdoException(PDOException $e): bool
    {
        $sqlState = is_scalar($e->errorInfo[0] ?? '') ? (string) ($e->errorInfo[0] ?? '') : '';
        $driverCode = $e->errorInfo[1] ?? null;

        if (!is_int($driverCode)) {
            return false;
        }

        foreach (self::ALLOWED_RAW_PDO_ERRORS as [$allowedState, $allowedCode]) {
            if ($sqlState === $allowedState && $driverCode === $allowedCode) {
                return true;
            }
        }

        return false;
    }
}
