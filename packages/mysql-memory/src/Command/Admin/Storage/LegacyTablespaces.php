<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Admin\Storage;

use MySqlMemory\Command\Admin\TablespaceCommand;
use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Session\Session;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\AlterTablespaceAccess;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\AlterTablespaceDatafile;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\CreateTablespace;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\DropTablespace;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\EngineOption;
use SqlSemantics\Statement\Statement;

/**
 * Executes MySQL 5.7 general tablespace requests with their legacy engine checks and conditions.
 *
 * InnoDB supports creation and removal, but refuses ALTER before looking up
 * the tablespace. An explicit engine is resolved before existence checks.
 * Observed through SQL on MySQL 5.7.44, including creation and removal histories.
 *
 * @visibility MySqlMemory
 */
final class LegacyTablespaces
{
    /**
     * Handles a 5.7 request, or answers false for another release or command family.
     *
     * @throws SqlError When the engine or storage operation is refused
     */
    public function execute(Statement $statement, Session $session, Context $context): bool
    {
        if ($session->settings()->release() !== GrammarRelease::MySql5744) {
            return false;
        }
        if (!$statement instanceof CreateTablespace && !$statement instanceof DropTablespace && !$statement instanceof AlterTablespaceDatafile && !$statement instanceof AlterTablespaceAccess) {
            return false;
        }
        $options = $statement instanceof AlterTablespaceAccess ? [] : $statement->options;
        $engine = (new LegacySupport())->engine($options, $session, $context);
        if ($engine !== 'InnoDB') {
            throw SchemaError::IllegalCreateOption->error($engine, 'TABLESPACE or LOGFILE GROUP');
        }
        if ($statement instanceof AlterTablespaceDatafile || $statement instanceof AlterTablespaceAccess) {
            throw SchemaError::EngineUnsupportedOperation->error('ALTER TABLESPACE');
        }
        if ($statement instanceof CreateTablespace) {
            $this->create($statement, $session);
        } else {
            $this->drop($statement, $session);
        }

        return true;
    }

    /**
     * Creates a tablespace after engine resolution, retaining legacy datafile conditions.
     *
     * @throws SqlError When the name, datafile or creation options are refused
     */
    public function create(CreateTablespace $statement, Session $session): void
    {
        $options = array_values(array_filter($statement->options, static fn ($option): bool => !$option instanceof EngineOption));
        try {
            (new TablespaceCommand())->create(new CreateTablespace($statement->name, $statement->datafile, $statement->logfileGroup, $options), $session->instance->registry);
        } catch (SqlError $error) {
            if ($error->error !== SchemaError::WrongFileName) {
                throw $error;
            }
            $name = $statement->name->value;
            $file = $statement->datafile === null ? '' : (new \MySqlMemory\Command\Admin\Literals())->bytes($statement->datafile);
            throw new SqlError(SchemaError::WrongFileName, SchemaError::WrongFileName->message(explode("\0", $file, 2)[0]), $error, [
                [SchemaError::CreateFilegroupFailed->value, SchemaError::CreateFilegroupFailed->message('TABLESPACE ' . $name)],
                [SchemaError::IllegalHa->value, SchemaError::IllegalHa->message($name)],
            ]);
        }
    }

    /**
     * Drops an existing tablespace, allowing a resolved explicit engine.
     *
     * @throws SqlError When the tablespace is missing or cannot be removed
     */
    public function drop(DropTablespace $statement, Session $session): void
    {
        $name = $statement->name->value;
        $command = new TablespaceCommand();
        if (!$command->exists($name, $session->instance->registry)) {
            throw new SqlError(SchemaError::DropFilegroupFailed, SchemaError::DropFilegroupFailed->message('TABLESPACE ' . $name), null, [[SchemaError::TablespaceUnavailable->value, SchemaError::TablespaceUnavailable->message($name)]]);
        }
        $options = array_values(array_filter($statement->options, static fn ($option): bool => !$option instanceof EngineOption));
        $command->drop(new DropTablespace($statement->name, $options), $session->instance->registry);
    }
}
