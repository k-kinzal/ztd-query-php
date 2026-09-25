<?php

declare(strict_types=1);

namespace SqlSemantics\Schema\Column;

use Override;

/**
 * An integer value supplied by the table auto-increment mechanism.
 *
 * @visibility public
  * @example Inspecting AutoIncrementColumn
 *     $table = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(id INTEGER GENERATED ALWAYS AS IDENTITY (START WITH 5 INCREMENT BY 2), name TEXT COLLATE "C")')->tables[0];
 *     $mysql = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE t(id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(10) CHARACTER SET utf8mb4)')->tables[0];
 *     $mysql->columns[0]->generation instanceof \SqlSemantics\Schema\Column\AutoIncrementColumn // => true
 *     $serial = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE s(id INT SERIAL DEFAULT VALUE)')->tables[0];
 *     $serial->columns[0]->generation->serialDefault // => true
 *     $type = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TABLE s(id SERIAL)')->tables[0];
 *     $type->columns[0]->generation->serialType // => true
 *     $type->columns[0]->type->name // => 'bigint unsigned'
 */
final class AutoIncrementColumn implements Generation
{
    /**
     * Constructs a valid declaration.
     *
     * @param bool $serialDefault Whether MySQL SERIAL DEFAULT VALUE declared the column, which also declares it NOT
     *                            NULL and gives it a unique key, rather than AUTO_INCREMENT
     * @param bool $serialType Whether the MySQL SERIAL type declared the column, which stands for BIGINT UNSIGNED NOT
     *                         NULL AUTO_INCREMENT UNIQUE
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(
        public readonly bool $serialDefault = false,
        public readonly bool $serialType = false,
    ) {
        if ($serialDefault && $serialType) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('A counter column is declared by one spelling: AUTO_INCREMENT, SERIAL DEFAULT VALUE, or the SERIAL type.');
        }
    }

    /**
     * Returns no generation expression: the storage engine supplies this column.
     */
    #[Override]
    public function expressions(): array
    {
        return [];
    }
}
