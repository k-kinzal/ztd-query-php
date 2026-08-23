<?php

declare(strict_types=1);

namespace ZtdQuery\Platform;

use ZtdQuery\Schema\TableDefinition;

interface CopySupport
{
    public function tableName(string $relation): string;

    public function target(string $relation, ?string $fields, TableDefinition $definition): CopyTarget;

    public function selectSql(CopyTarget $target): string;

    public function insertSql(CopyTarget $target, int $rowCount, bool $overrideSystemValue): string;

    /** @param list<mixed> $values */
    public function encodeRow(array $values, string $separator, string $nullAs): string;

    /** @return list<string|null> */
    public function decodeRow(string $row, string $separator, string $nullAs): array;

    public function isCopyStatement(string $sql): bool;
}
