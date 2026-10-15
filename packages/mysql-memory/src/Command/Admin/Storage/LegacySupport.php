<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Admin\Storage;

use MySqlMemory\Command\Show\Server\ServerCatalog;
use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Session\Session;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\AlterLogfileGroup;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\AlterTablespaceAccess;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\AlterTablespaceDatafile;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\CreateLogfileGroup;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\CreateTablespace;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\DropLogfileGroup;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\DropTablespace;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\EngineOption;
use SqlSemantics\Statement\Statement;

/**
 * Resolves the storage engine of MySQL 5.6 tablespace and logfile-group commands.
 *
 * Its built-in engines reject both families before inspecting files or existing
 * tablespaces. An omitted engine uses the session default. Unknown names raise
 * an error with NO_ENGINE_SUBSTITUTION, otherwise warn and use that default.
 * Observed on a MySQL 5.6.51 server with InnoDB, MyISAM and MEMORY.
 *
 * @visibility MySqlMemory
 */
final class LegacySupport
{
    /**
     * Refuses unsupported storage operations after resolving the session's engine.
     *
     * @throws \MySqlMemory\Error\SqlError When the selected legacy engine cannot execute the command
     */
    public function check(Statement $statement, Session $session, Context $context): void
    {
        if ($session->settings()->release() !== GrammarRelease::MySql5651) {
            return;
        }
        $options = match (true) {
            $statement instanceof CreateTablespace, $statement instanceof AlterTablespaceDatafile,
            $statement instanceof DropTablespace, $statement instanceof CreateLogfileGroup,
            $statement instanceof AlterLogfileGroup, $statement instanceof DropLogfileGroup => $statement->options,
            $statement instanceof AlterTablespaceAccess => [],
            default => null,
        };
        if ($options === null) {
            return;
        }
        $default = (string) $session->variables->read('default_storage_engine');
        $engine = $default;
        foreach ($options as $option) {
            if (!$option instanceof EngineOption) {
                continue;
            }
            $named = $option->engine->value;
            $engine = ServerCatalog::shared()->engine($named);
            if ($engine === null) {
                if ($context->modes->has('NO_ENGINE_SUBSTITUTION')) {
                    throw SchemaError::UnknownStorageEngine->error($named);
                }
                $context->warning(SchemaError::UnknownStorageEngine, $named);
                $engine = $default;
            }
        }

        throw SchemaError::IllegalCreateOption->error($engine, 'TABLESPACE or LOGFILE GROUP');
    }
}
