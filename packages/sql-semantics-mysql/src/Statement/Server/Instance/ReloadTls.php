<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Instance;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * `RELOAD TLS [FOR CHANNEL channel] [NO ROLLBACK ON ERROR]`: reconfigure the TLS context of a connection interface (MySQL 8.0.16 and later).
 *
 * Mirrors ALTER_INSTANCE_RELOAD_TLS_ROLLBACK_ON_ERROR and, with NO ROLLBACK
 * ON ERROR, ALTER_INSTANCE_RELOAD_TLS. Without a channel the main
 * connection interface (mysql_main) is reconfigured; FOR CHANNEL is
 * accepted from MySQL 8.0.21. On an error the server keeps the old context
 * unless NO ROLLBACK ON ERROR is written.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-instance.html.
 *
 * @visibility public
 * @example Reloading the TLS context of the administrative interface
 *     $reload = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('ALTER INSTANCE RELOAD TLS FOR CHANNEL mysql_admin NO ROLLBACK ON ERROR');
 *     [$reload->statement->action->channel?->value, $reload->statement->action->noRollbackOnError] // => ['mysql_admin', true]
 */
final class ReloadTls implements InstanceAction
{
    use Snapshot;

    /**
     * @param Name|null $channel The channel of FOR CHANNEL, when written
     * @param bool $noRollbackOnError Whether NO ROLLBACK ON ERROR is written
     */
    public function __construct(public readonly ?Name $channel = null, public readonly bool $noRollbackOnError = false)
    {
    }

    /**
     * Writes the action.
     */
    public function render(Output $out): void
    {
        $out->keyword('RELOAD', 'TLS');
        if ($this->channel !== null) {
            $out->keyword('FOR', 'CHANNEL')->name($this->channel, NameUse::Identifier);
        }
        if ($this->noRollbackOnError) {
            $out->keyword('NO', 'ROLLBACK', 'ON', 'ERROR');
        }
    }
}
