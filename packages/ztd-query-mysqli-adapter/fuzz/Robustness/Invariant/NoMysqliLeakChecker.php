<?php

declare(strict_types=1);

namespace Fuzz\Robustness\Invariant;

use mysqli_sql_exception;
use ZtdQuery\Adapter\Mysqli\ZtdMysqliException;
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

final class NoMysqliLeakChecker
{
    /** @var array<int, int> */
    private const ALLOWED_RAW_MYSQLI_ERRORS = [
        1054, // Unknown column
        1146, // Table doesn't exist
        1109, // Unknown table in multi-table statement
        1327, // Undeclared variable
    ];

    /** @var callable(string): void */
    private $executor;

    /**
     * @param callable(string): void $executor Callable that executes SQL via ZtdMysqli
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
               } catch (ZtdMysqliException) {
                   return null;
               } catch (mysqli_sql_exception $e) {
                   if ($this->isAllowedRawMysqliException($e)) {
                       return null;
                   }

                   return new InvariantViolation(
                       'INV-L5-01',
                       'Unexpected raw mysqli_sql_exception escaped ZTD layer',
                       $sql,
                       [
                           'exception_class' => get_class($e),
                           'driver_code' => $e->getCode(),
                           'exception_message' => $e->getMessage(),
                       ]
                   );
               }
    }

    private function isAllowedRawMysqliException(mysqli_sql_exception $e): bool
    {
        $driverCode = $e->getCode();

        foreach (self::ALLOWED_RAW_MYSQLI_ERRORS as $allowedCode) {
            if ($driverCode === $allowedCode) {
                return true;
            }
        }

        return false;
    }
}
