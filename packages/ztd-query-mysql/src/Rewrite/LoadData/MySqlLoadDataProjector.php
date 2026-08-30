<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Rewrite\LoadData;

use PhpMyAdmin\SqlParser\Statements\LoadStatement;
use ZtdQuery\Exception\UnknownSchemaException;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Schema\TableDefinitionRegistry;

/**
 * Writes a LOAD DATA out as the INSERT it amounts to.
 *
 * ZTD does not write to the database, so a LOAD DATA has to become something
 * the shadow can simulate. Reading the file here and writing the rows out as
 * an INSERT means everything downstream -- constraints, keys, cascades --
 * happens through the one path every other write goes through.
 */
final class MySqlLoadDataProjector
{
    /**
     * @param TableDefinitionRegistry $registry What each table holds
     * @param MySqlLoadDataDelimiters $delimiters Reads the file as the statement said it is delimited
     * @param MySqlLoadDataTargets $targets Decides where the values read are going
     * @param MySqlLoadDataInsert $insert Writes the INSERT the rows amount to
     */
    public function __construct(
        private readonly TableDefinitionRegistry $registry,
        private readonly MySqlLoadDataDelimiters $delimiters = new MySqlLoadDataDelimiters(),
        private readonly MySqlLoadDataTargets $targets = new MySqlLoadDataTargets(),
        private readonly MySqlLoadDataInsert $insert = new MySqlLoadDataInsert(),
    ) {
    }

    /**
     * Answers the INSERT a LOAD DATA amounts to.
     *
     * @param string $sql Statement being simulated
     * @param LoadStatement $statement The statement, as the parser reads it
     *
     * @return string The INSERT it amounts to
     *
     * @throws UnknownSchemaException When nothing has declared the table it loads into
     * @throws UnsupportedSqlException When the statement asks for something ZTD cannot simulate
     */
    public function project(string $sql, LoadStatement $statement): string
    {
        $tableName = $statement->table?->table;
        if (!is_string($tableName) || $tableName === '') {
            throw new UnsupportedSqlException($sql, 'Cannot resolve LOAD DATA target');
        }
        $definition = $this->registry->get($tableName);
        if ($definition === null) {
            throw new UnknownSchemaException($sql, $tableName, 'table');
        }
        if ($statement->partition !== null) {
            throw new UnsupportedSqlException($sql, 'LOAD DATA PARTITION cannot be simulated safely');
        }
        if ($statement->charset_name !== null) {
            throw new UnsupportedSqlException($sql, 'LOAD DATA CHARACTER SET conversion is not supported');
        }

        $contents = $this->contentsOf($sql, $statement);

        $fieldTerminator = $this->delimiters->optionValue($statement->fields_options, 'TERMINATED BY', "\t");
        $enclosure = $this->delimiters->optionValue($statement->fields_options, 'ENCLOSED BY', '');
        $escape = $this->delimiters->optionValue($statement->fields_options, 'ESCAPED BY', '\\');
        $linePrefix = $this->delimiters->optionValue($statement->lines_options, 'STARTING BY', '');
        $lineTerminator = $this->delimiters->optionValue($statement->lines_options, 'TERMINATED BY', "\n");
        if ($fieldTerminator === '' || $lineTerminator === '') {
            throw new UnsupportedSqlException($sql, 'LOAD DATA fixed-row input is not supported');
        }
        if (strlen($enclosure) > 1 || strlen($escape) > 1) {
            throw new UnsupportedSqlException($sql, 'LOAD DATA enclosure and escape must be single-byte values');
        }

        $targets = $this->targets->of($statement, $definition, $sql);
        $setOperations = $this->targets->setOperations($statement, $definition, $sql);
        $records = array_slice(
            $this->delimiters->records($contents, $fieldTerminator, $lineTerminator, $enclosure, $escape),
            $this->targets->ignoreRows($statement, $sql),
        );

        $rows = [];
        foreach ($records as $record) {
            if ($linePrefix !== '' && !str_starts_with($record, $linePrefix)) {
                continue;
            }
            if ($linePrefix !== '') {
                $record = substr($record, strlen($linePrefix));
            }
            $rows[] = $this->targets->rowOf(
                $targets,
                $setOperations,
                $this->delimiters->fields($record, $fieldTerminator, $enclosure, $escape),
            );
        }

        return $this->insert->sqlFor($statement, $tableName, $definition, $targets, $setOperations, $rows);
    }

    /**
     * Reads the file the statement loads from.
     *
     * ZTD reads the file itself rather than asking the database to, so a file
     * the database could read and ZTD cannot is a statement ZTD refuses rather
     * than one it silently simulates as loading nothing.
     *
     * @param string $sql Statement being simulated
     * @param LoadStatement $statement The statement, as the parser reads it
     *
     * @return string The file, as it was read
     *
     * @throws UnsupportedSqlException When there is no file there, or it cannot be read
     */
    public function contentsOf(string $sql, LoadStatement $statement): string
    {
        if ($statement->file_name === null) {
            throw new UnsupportedSqlException($sql, 'LOAD DATA input file is not readable');
        }
        $path = get_object_vars($statement->file_name)['file'] ?? null;
        if (!is_string($path) || $path === '' || !is_file($path) || !is_readable($path)) {
            throw new UnsupportedSqlException($sql, 'LOAD DATA input file is not readable');
        }
        $contents = file_get_contents($path);
        if (!is_string($contents)) {
            throw new UnsupportedSqlException($sql, 'LOAD DATA input file could not be read');
        }

        return $contents;
    }
}
