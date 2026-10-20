<?php

declare(strict_types=1);

namespace SqlFixture\Analysis;

use SqlFixture\Schema\Exception\ExpectedCreateTableException;
use SqlFixture\Schema\Exception\InvalidSqlException;
use SqlSemantics\Diagnostic\AnalysisException;
use SqlSemantics\Facade\Semantics;
use SqlSemantics\Statement\Declaration\Column;
use SqlSemantics\Statement\Declaration\RelationKind;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Operation;

/**
 * Finds the one statement of an input that creates a table, and the table it declares.
 *
 * The input is analyzed in an open context, so a foreign key may name a table
 * the input does not declare. Any form of CREATE TABLE is found, including
 * one that copies another table or a query; a statement the server would
 * refuse is reported through the diagnostics of the analysis and rejected.
 *
 * @visibility root
 */
final class CreateTableOperation
{
    /**
     * Returns the analyzed statement of the input that creates a base table.
     *
     * @throws InvalidSqlException
     * @throws ExpectedCreateTableException
     */
    public function locate(Semantics $semantics, string $sql): Operation
    {
        try {
            $operations = $semantics->analyzeAll($sql);
        } catch (AnalysisException $exception) {
            throw new InvalidSqlException($sql, $exception->getMessage(), $exception);
        }
        if ($operations === []) {
            throw new InvalidSqlException($sql, 'No statements found');
        }

        $creates = array_values(array_filter($operations, fn (Operation $operation): bool => $this->table($operation) !== null));
        if ($creates === []) {
            throw new ExpectedCreateTableException($sql);
        }
        if (count($creates) > 1) {
            throw new InvalidSqlException($sql, 'More than one CREATE TABLE statement');
        }

        $diagnostic = $creates[0]->facts->diagnostics[0] ?? null;
        if ($diagnostic !== null) {
            throw new InvalidSqlException($sql, $diagnostic->message());
        }

        return $creates[0];
    }

    /**
     * Returns the base table the statement declares, or null when it declares none.
     */
    public function table(Operation $operation): ?Table
    {
        foreach ($operation->declarations() as $table) {
            if ($table->kind === RelationKind::BaseTable) {
                return $table;
            }
        }

        return null;
    }

    /**
     * Returns the name of the declared table without its schema.
     */
    public function tableName(Operation $operation): string
    {
        return $this->table($operation)->name->name->value ?? '';
    }

    /**
     * Returns the columns the statement declares, keyed by name.
     *
     * @return array<string, Column>
     */
    public function columns(Operation $operation): array
    {
        $columns = [];
        foreach ($this->table($operation)->columns ?? [] as $column) {
            $columns[$column->name->value] = $column;
        }

        return $columns;
    }

    /**
     * Returns the declared name of the column a written name denotes, compared as the dialect compares column names.
     *
     * A name that denotes no declared column is returned as written.
     */
    public function columnName(Operation $operation, string $written): string
    {
        $matches = $this->table($operation)?->matchingColumns($written, $operation->context->columnNames) ?? [];

        return $matches === [] ? $written : $matches[0]->name->value;
    }
}
