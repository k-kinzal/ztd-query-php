<?php

declare(strict_types=1);

namespace ZtdQuery\Adapter\Pdo\Session;

use PDO;
use ZtdQuery\Adapter\Pdo\ZtdPdoException;
use ZtdQuery\Platform\CopySupport;
use ZtdQuery\Platform\CopyTarget;
use ZtdQuery\Session;

/**
 * Carries out PostgreSQL's COPY through ZTD instead of through the server.
 *
 * COPY moves rows in and out of a table without a statement ZTD can shadow, so
 * a COPY the server ran would read past the shadow and write past it. Every
 * form of it is answered here as the SELECT or INSERT that says the same thing,
 * which ZTD can shadow like any other statement.
 *
 * @visibility ZtdQuery\Adapter\Pdo
 */
final class PostgreSqlCopy
{
    /**
     * Binds the COPY to the session that says what ZTD makes of it.
     *
     * The connection is passed to each call rather than held, because the one
     * that carries a COPY out is the ZTD connection this belongs to: holding it
     * would make the two point at each other, and a PDO that only the cycle
     * collector frees is freed at a moment no one chose.
     *
     * @param Session $session Session that says whether ZTD is shadowing the table
     */
    public function __construct(private readonly Session $session)
    {
    }

    /**
     * Refuses a COPY written as raw SQL.
     *
     * @param string $sql Statement as it was written
     *
     * @throws ZtdPdoException When the statement is a COPY the server would run itself
     */
    public function guardRaw(string $sql): void
    {
        if ($this->session->copySupport()?->isCopyStatement($sql) === true) {
            throw new ZtdPdoException(
                'ZTD Write Protection: Raw PostgreSQL COPY cannot preserve shadow isolation; '
                . 'use the pgsqlCopyToArray(), pgsqlCopyFromArray(), pgsqlCopyToFile(), or pgsqlCopyFromFile() methods.',
            );
        }
    }

    /**
     * Answers the table's rows as COPY would have written them out.
     *
     * @param PDO $connection Connection the rewritten statement runs on
     * @param string $tableName Relation to read, as the caller named it
     * @param string $separator Field separator COPY writes between values
     * @param string $nullAs Text COPY writes where a value is null
     * @param ?string $fields Column list as the caller wrote it, or null for every column
     *
     * @return list<string>|false One encoded line per row, or false where the read did not run
     *
     * @throws ZtdPdoException When the dialect has no COPY, the table is undescribed, or a row cannot be read
     */
    public function toArray(PDO $connection, string $tableName, string $separator = "\t", string $nullAs = '\\N', ?string $fields = null): array|false
    {
        [$copy, $target] = $this->target(
            $tableName,
            $fields,
        );
        $statement = $connection->query($copy->selectSql($target));
        if ($statement === false) {
            return false;
        }

        $separatorText = $separator;
        $nullText = $nullAs;

        $rows = [];
        while (($values = $statement->fetch(PDO::FETCH_NUM)) !== false) {
            if (!is_array($values)) {
                throw new ZtdPdoException('PostgreSQL COPY query returned an invalid row.');
            }
            $rows[] = $copy->encodeRow(array_values($values), $separatorText, $nullText);
        }

        return $rows;
    }

    /**
     * Writes encoded lines into the table as COPY would have read them in.
     *
     * @param PDO $connection Connection the rewritten statement runs on
     * @param string $tableName Relation to write, as the caller named it
     * @param iterable<int|string, string> $rows One encoded line per row
     * @param string $separator Field separator COPY reads between values
     * @param string $nullAs Text COPY reads as a null value
     * @param ?string $fields Column list as the caller wrote it, or null for every column
     *
     * @return bool Whether every row was written
     *
     * @throws ZtdPdoException When the dialect has no COPY, the table is undescribed, or a line does not fit it
     */
    public function fromArray(PDO $connection, string $tableName, iterable $rows, string $separator = "\t", string $nullAs = '\\N', ?string $fields = null): bool
    {
        [$copy, $target] = $this->target(
            $tableName,
            $fields,
        );
        $separatorText = $separator;
        $nullText = $nullAs;

        $parameters = [];
        $rowCount = 0;
        foreach ($rows as $row) {
            $values = $copy->decodeRow($row, $separatorText, $nullText);
            if (count($values) !== count($target->columns)) {
                throw new ZtdPdoException(sprintf(
                    'PostgreSQL COPY row has %d fields, but %d fields are required.',
                    count($values),
                    count($target->columns),
                ));
            }
            foreach ($values as $value) {
                $parameters[] = $value;
            }
            $rowCount++;
        }
        if ($rowCount === 0) {
            return true;
        }

        $statement = $connection->prepare($copy->insertSql($target, $rowCount, !$this->session->isEnabled()));

        return $statement !== false && $statement->execute($parameters);
    }

    /**
     * Writes the table's rows into a file as COPY would have written them out.
     *
     * @param PDO $connection Connection the rewritten statement runs on
     * @param string $tableName Relation to read, as the caller named it
     * @param string $filename File to write the encoded lines to
     * @param string $separator Field separator COPY writes between values
     * @param string $nullAs Text COPY writes where a value is null
     * @param ?string $fields Column list as the caller wrote it, or null for every column
     *
     * @return bool Whether the file was written
     *
     * @throws ZtdPdoException When the dialect has no COPY, the table is undescribed, or a row cannot be read
     */
    public function toFile(PDO $connection, string $tableName, string $filename, string $separator = "\t", string $nullAs = '\\N', ?string $fields = null): bool
    {
        $rows = $this->toArray($connection, $tableName, $separator, $nullAs, $fields);
        if ($rows === false) {
            return false;
        }

        return file_put_contents($filename, implode('', $rows)) !== false;
    }

    /**
     * Reads encoded lines out of a file and writes them into the table.
     *
     * @param PDO $connection Connection the rewritten statement runs on
     * @param string $tableName Relation to write, as the caller named it
     * @param string $filename File to read the encoded lines from
     * @param string $separator Field separator COPY reads between values
     * @param string $nullAs Text COPY reads as a null value
     * @param ?string $fields Column list as the caller wrote it, or null for every column
     *
     * @return bool Whether every row in the file was written
     *
     * @throws ZtdPdoException When the dialect has no COPY, the table is undescribed, or a line does not fit it
     */
    public function fromFile(PDO $connection, string $tableName, string $filename, string $separator = "\t", string $nullAs = '\\N', ?string $fields = null): bool
    {
        $path = $filename;
        if (!is_readable($path)) {
            return false;
        }
        $rows = file($path);
        if ($rows === false) {
            return false;
        }

        return $this->fromArray($connection, $tableName, $rows, $separator, $nullAs, $fields);
    }

    /**
     * Answers how this dialect writes COPY and what the named table holds.
     *
     * @param string $tableName Relation as the caller named it
     * @param string|null $fields Column list as the caller wrote it, or null for every column
     *
     * @return array{CopySupport, CopyTarget} What writes COPY, and what it writes against
     *
     * @throws ZtdPdoException When the dialect has no COPY, or nothing has described the table
     */
    public function target(string $tableName, ?string $fields): array
    {
        $copy = $this->session->copySupport();
        if ($copy === null) {
            throw new ZtdPdoException('PostgreSQL COPY methods require the PDO PostgreSQL driver.');
        }

        $target = $this->session->copyTarget($tableName, $fields);
        if ($target === null) {
            throw new ZtdPdoException(sprintf(
                'PostgreSQL COPY cannot resolve the schema for table "%s".',
                $tableName,
            ));
        }

        return [$copy, $target];
    }

}
