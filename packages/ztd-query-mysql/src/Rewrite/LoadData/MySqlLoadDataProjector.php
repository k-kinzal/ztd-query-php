<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Rewrite\LoadData;

use PhpMyAdmin\SqlParser\Statements\LoadStatement;
use ZtdQuery\Exception\UnknownSchemaException;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\MySql\Sql\MySqlIdentifierQuoter;
use ZtdQuery\Platform\MySql\Sql\Value\MySqlValueRenderer;
use ZtdQuery\Schema\TableDefinitionRegistry;

/**
 * Projects LOAD DATA input into the regular simulated INSERT/REPLACE pipeline.
 */
final class MySqlLoadDataProjector
{
    private MySqlIdentifierQuoter $quoter;
    private MySqlValueRenderer $valueRenderer;

    /**
     * Configure the dependencies used by this operation.
     */
    public function __construct(private readonly TableDefinitionRegistry $registry)
    {
        $this->quoter = new MySqlIdentifierQuoter();
        $this->valueRenderer = new MySqlValueRenderer();
    }

    /**
     * Project for the supplied MySQL input.
     * @throws UnsupportedSqlException
     * @throws UnknownSchemaException
     */
    public function project(string $sql, LoadStatement $statement): string
    {
        $tableName = $statement->table?->table;
        if (!is_string($tableName)) {
            throw new UnsupportedSqlException($sql, 'Cannot resolve LOAD DATA target');
        }
        if ($tableName === '') {
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

        $contents = (new InputFile())->read($statement, $sql);

        $format = InputFormat::fromStatement($statement, $sql);

        $targets = (new ColumnMapping())->inputTargets($statement, $definition, $sql);
        $setOperations = (new ColumnMapping())->setOperations($statement, $definition, $sql);
        $ignoreRows = (new ColumnMapping())->ignoreRows($statement, $sql);
        $records = (new RecordParser())->splitRecords($contents, $format);
        $records = array_slice($records, $ignoreRows);

        $rows = [];
        foreach ($records as $record) {
            if ($format->linePrefix !== '' && !str_starts_with($record, $format->linePrefix)) {
                continue;
            }
            if ($format->linePrefix !== '') {
                $record = substr($record, strlen($format->linePrefix));
            }
            $values = (new RecordParser())->parseFields($record, $format);
            $rows[] = (new RowProjector($this->valueRenderer))->projectRow($targets, $setOperations, $values);
        }

        return (new InsertQuery($this->quoter))->buildInsertSql($statement, $tableName, $definition, $targets, $setOperations, $rows);
    }

}
