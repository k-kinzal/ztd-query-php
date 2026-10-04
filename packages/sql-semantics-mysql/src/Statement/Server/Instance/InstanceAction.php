<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Instance;

use SqlSemantics\Statement\Node;

/**
 * The action of ALTER INSTANCE.
 *
 * Mirrors enum alter_instance_action_enum of the server. The names the
 * grammar reads as identifiers (INNODB, BINLOG, REDO_LOG) are kept as
 * written; the server compares them case-insensitively.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-instance.html.
 *
 * @visibility public
 * @example Reading the action of ALTER INSTANCE
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('ALTER INSTANCE RELOAD KEYRING')->statement->action instanceof \SqlSemantics\Platform\MySql\Statement\Server\Instance\ReloadKeyring // => true
 */
interface InstanceAction extends Node
{
}
