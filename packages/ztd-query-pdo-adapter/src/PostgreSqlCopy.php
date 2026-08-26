<?php

declare(strict_types=1);

namespace ZtdQuery\Adapter\Pdo;

use PDO;
use Traversable;
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
 * The arguments arrive as PDO's own driver methods took them, which is to say
 * as anything at all, so each one is read as a string here before it is used.
 */
final class PostgreSqlCopy
{
    /**
     * Binds the COPY to the connection that will carry it out.
     *
     * @param PDO $connection Connection the rewritten statements run on
     * @param Session $session Session that says whether ZTD is shadowing the table
     */
    public function __construct(
        private readonly PDO $connection,
        private readonly Session $session,
    ) {
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
     * @param mixed $tableName Relation to read, as the caller named it
     * @param mixed $separator Field separator COPY writes between values
     * @param mixed $nullAs Text COPY writes where a value is null
     * @param mixed $fields Column list as the caller wrote it, or null for every column
     *
     * @return list<string>|false One encoded line per row, or false where the read did not run
     *
     * @throws ZtdPdoException When the dialect has no COPY, the table is undescribed, or a row cannot be read
     */
    public function toArray(mixed $tableName, mixed $separator = "\t", mixed $nullAs = '\\N', mixed $fields = null): array|false
    {
        [$copy, $target] = $this->target(
            $this->stringArgument($tableName, 'tableName'),
            $this->optionalStringArgument($fields, 'fields'),
        );
        $statement = $this->connection->query($copy->selectSql($target));
        if ($statement === false) {
            return false;
        }

        $separatorText = $this->stringArgument($separator, 'separator');
        $nullText = $this->stringArgument($nullAs, 'nullAs');

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
     * @param mixed $tableName Relation to write, as the caller named it
     * @param array<mixed>|Traversable<mixed, mixed> $rows One encoded line per row
     * @param mixed $separator Field separator COPY reads between values
     * @param mixed $nullAs Text COPY reads as a null value
     * @param mixed $fields Column list as the caller wrote it, or null for every column
     *
     * @return bool Whether every row was written
     *
     * @throws ZtdPdoException When the dialect has no COPY, the table is undescribed, or a line does not fit it
     */
    public function fromArray(mixed $tableName, array|Traversable $rows, mixed $separator = "\t", mixed $nullAs = '\\N', mixed $fields = null): bool
    {
        [$copy, $target] = $this->target(
            $this->stringArgument($tableName, 'tableName'),
            $this->optionalStringArgument($fields, 'fields'),
        );
        $separatorText = $this->stringArgument($separator, 'separator');
        $nullText = $this->stringArgument($nullAs, 'nullAs');

        $parameters = [];
        $rowCount = 0;
        foreach ($rows as $row) {
            if (!is_string($row)) {
                throw new ZtdPdoException(sprintf('PostgreSQL COPY rows must be strings, %s given.', get_debug_type($row)));
            }
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

        $statement = $this->connection->prepare($copy->insertSql($target, $rowCount, !$this->session->isEnabled()));

        return $statement !== false && $statement->execute($parameters);
    }

    /**
     * Writes the table's rows into a file as COPY would have written them out.
     *
     * @param mixed $tableName Relation to read, as the caller named it
     * @param mixed $filename File to write the encoded lines to
     * @param mixed $separator Field separator COPY writes between values
     * @param mixed $nullAs Text COPY writes where a value is null
     * @param mixed $fields Column list as the caller wrote it, or null for every column
     *
     * @return bool Whether the file was written
     *
     * @throws ZtdPdoException When the dialect has no COPY, the table is undescribed, or a row cannot be read
     */
    public function toFile(mixed $tableName, mixed $filename, mixed $separator = "\t", mixed $nullAs = '\\N', mixed $fields = null): bool
    {
        $rows = $this->toArray($tableName, $separator, $nullAs, $fields);
        if ($rows === false) {
            return false;
        }

        return file_put_contents($this->stringArgument($filename, 'filename'), implode('', $rows)) !== false;
    }

    /**
     * Reads encoded lines out of a file and writes them into the table.
     *
     * @param mixed $tableName Relation to write, as the caller named it
     * @param mixed $filename File to read the encoded lines from
     * @param mixed $separator Field separator COPY reads between values
     * @param mixed $nullAs Text COPY reads as a null value
     * @param mixed $fields Column list as the caller wrote it, or null for every column
     *
     * @return bool Whether every row in the file was written
     *
     * @throws ZtdPdoException When the dialect has no COPY, the table is undescribed, or a line does not fit it
     */
    public function fromFile(mixed $tableName, mixed $filename, mixed $separator = "\t", mixed $nullAs = '\\N', mixed $fields = null): bool
    {
        $path = $this->stringArgument($filename, 'filename');
        if (!is_readable($path)) {
            return false;
        }
        $rows = file($path);
        if ($rows === false) {
            return false;
        }

        return $this->fromArray($tableName, $rows, $separator, $nullAs, $fields);
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

    /**
     * Answers an argument as the string COPY reads it as.
     *
     * @param mixed $value Argument as the caller passed it
     * @param string $name Name the argument is written under
     *
     * @return string The same argument, as a string
     *
     * @throws ZtdPdoException When the argument is not a string
     */
    public function stringArgument(mixed $value, string $name): string
    {
        if (!is_string($value)) {
            throw new ZtdPdoException(sprintf('PostgreSQL COPY argument $%s must be a string, %s given.', $name, get_debug_type($value)));
        }

        return $value;
    }

    /**
     * Answers an argument as the string COPY reads it as, where it was given one.
     *
     * @param mixed $value Argument as the caller passed it, or null
     * @param string $name Name the argument is written under
     *
     * @return string|null The same argument as a string, or null where none was given
     *
     * @throws ZtdPdoException When the argument is neither a string nor null
     */
    public function optionalStringArgument(mixed $value, string $name): ?string
    {
        if ($value === null) {
            return null;
        }

        return $this->stringArgument($value, $name);
    }
}
