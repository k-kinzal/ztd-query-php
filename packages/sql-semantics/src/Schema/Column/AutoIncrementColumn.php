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
 */
final class AutoIncrementColumn implements Generation
{
    /**
     * Constructs a valid declaration.

     */
    public function __construct(
    ) {
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
