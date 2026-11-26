<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Storage\Option;

use SqlSemantics\Platform\MySql\Model\Statement\Node;

/**
 * An option of a tablespace, undo tablespace or log file group statement.
 *
 * Mirrors PT_alter_tablespace_option_base and its subclasses (MySQL 8.0)
 * and the fields of st_alter_tablespace (MySQL 5.x). Each statement accepts
 * the options its grammar rule lists.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-tablespace.html.
 *
 * @visibility SqlSemantics
 */
interface StorageOption extends Node
{
    /**
     * Answers the keyword that names the option; WAIT for both WAIT and NO_WAIT.
     */
    public function keyword(): string;
}
