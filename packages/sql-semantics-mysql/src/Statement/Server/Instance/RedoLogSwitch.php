<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Server\Instance;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\InvalidConstruction;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * `{ENABLE | DISABLE} INNODB REDO_LOG`: enable or disable InnoDB redo logging (MySQL 8.0.21 and later).
 *
 * Mirrors ALTER_INSTANCE_ENABLE_INNODB_REDO and
 * ALTER_INSTANCE_DISABLE_INNODB_REDO. The two names are written as
 * identifiers; any other names are a syntax error of the server and cannot
 * be constructed.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-instance.html.
 *
 * @visibility public
 * @example Disabling redo logging
 *     $switch = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('ALTER INSTANCE DISABLE innodb redo_log')->statement->action;
 *     [$switch->enable, $switch->engine->value] // => [false, 'innodb']
 */
final class RedoLogSwitch implements InstanceAction
{
    use Snapshot;

    /**
     * @param bool $enable Whether ENABLE (true) or DISABLE (false) is written
     * @param Name $engine The name INNODB, in any letter case
     * @param Name $log The name REDO_LOG, in any letter case
     * @throws InvalidConstruction When a name is not the one the server expects
     */
    public function __construct(public readonly bool $enable, public readonly Name $engine, public readonly Name $log)
    {
        Check::input(strtoupper($engine->value) === 'INNODB', 'ALTER INSTANCE ENABLE or DISABLE names INNODB.');
        Check::input(strtoupper($log->value) === 'REDO_LOG', 'ALTER INSTANCE ENABLE or DISABLE names REDO_LOG.');
    }

    /**
     * Writes the action.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->enable ? 'ENABLE' : 'DISABLE')->name($this->engine, NameUse::Identifier)->name($this->log, NameUse::Identifier);
    }
}
