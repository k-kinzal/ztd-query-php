<?php

declare(strict_types=1);

namespace ZtdQuery\Platform;

use ZtdQuery\Schema\TableDefinition;

/**
 * How a dialect writes COPY, where it has one at all.
 *
 * COPY moves rows in and out of a table without a statement per row, so
 * simulating it means knowing how the rows are written, how they are read
 * back, and what the statement is written against.
 */
interface CopySupport
{
    /**
     * Table name.
     *
     * @param string $relation
     * @return string
     */
    public function tableName(string $relation): string;

    /**
     * Target.
     *
     * @param string $relation
     * @param ?string $fields
     * @param TableDefinition $definition
     * @return CopyTarget
     */
    public function target(string $relation, ?string $fields, TableDefinition $definition): CopyTarget;

    /**
     * Select sql.
     *
     * @param CopyTarget $target
     * @return string
     */
    public function selectSql(CopyTarget $target): string;

    /**
     * Insert sql.
     *
     * @param CopyTarget $target
     * @param int $rowCount
     * @param bool $overrideSystemValue
     * @return string
     */
    public function insertSql(CopyTarget $target, int $rowCount, bool $overrideSystemValue): string;

    /**
     * @param list<mixed> $values
     */
    public function encodeRow(array $values, string $separator, string $nullAs): string;

    /**
     * @return list<string|null>
     */
    public function decodeRow(string $row, string $separator, string $nullAs): array;

    /**
     * Reports whether copy statement.
     *
     * @param string $sql
     * @return bool
     */
    public function isCopyStatement(string $sql): bool;
}
