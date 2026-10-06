<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Storage\Option;

use SqlSemantics\Statement\Node;

/**
 * An option of a tablespace, undo tablespace or log file group statement.
 *
 * Mirrors PT_alter_tablespace_option_base and its subclasses (MySQL 8.0)
 * and the fields of st_alter_tablespace (MySQL 5.x). Each statement accepts
 * the options its grammar rule lists.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-tablespace.html.
 *
 * @visibility public
 * @example Reading the keyword of an option
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze("CREATE TABLESPACE ts ADD DATAFILE 'ts.ibd' FILE_BLOCK_SIZE = 8192");
 *     $create->statement->options[0]->keyword() // => 'FILE_BLOCK_SIZE'
 */
interface StorageOption extends Node
{
    /**
     * Answers the keyword that names the option; WAIT for both WAIT and NO_WAIT.
     */
    public function keyword(): string;
}
