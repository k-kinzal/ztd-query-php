<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Instance;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Model\Statement\Statement;

/**
 * `ALTER INSTANCE action`: a request to change the running server instance (MySQL 5.7.11 and later).
 *
 * Mirrors PT_alter_instance (Sql_cmd_alter_instance). Rule:
 * MYSQL-ALTER-INSTANCE-001. The statement names no relation and has no
 * facts.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-instance.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class AlterInstance implements Statement
{
    use Snapshot;

    /**
     * @param InstanceAction $action The action
     */
    public function __construct(public readonly InstanceAction $action)
    {
    }

    /**
     * Has nothing to derive: the request names no relation and no value.
     */
    public function deriveStatement(Derivation $derivation): void
    {
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALTER', 'INSTANCE')->node($this->action);
    }
}
