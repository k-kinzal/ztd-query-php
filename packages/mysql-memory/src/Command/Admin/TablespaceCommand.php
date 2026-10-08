<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Admin;

use MySqlMemory\Command\Command;
use MySqlMemory\Command\Show\Server\ServerCatalog;
use MySqlMemory\Error\Family\AdministrationError;
use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Registry\Registry;
use MySqlMemory\Registry\Tablespace;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use Override;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\AlterTablespace;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\AlterTablespaceAccess;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\AlterTablespaceDatafile;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\AlterUndoTablespace;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\CreateTablespace;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\CreateUndoTablespace;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\DropTablespace;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\DropUndoTablespace;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\DatafileAction;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\EncryptionOption;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\EngineOption;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\SizeOption;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\SizeOptionKind;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\StorageOption;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\RenameTablespace;
use SqlSemantics\Statement\Operation;

/**
 * Executes the statements on InnoDB general and undo tablespaces: CREATE, ALTER, RENAME and DROP TABLESPACE, and CREATE, ALTER and DROP UNDO TABLESPACE.
 *
 * Each commits the open transaction. An ENGINE option must name an enabled storage engine
 * (ER_UNKNOWN_STORAGE_ENGINE), and InnoDB (ER_ILLEGAL_HA_CREATE_OPTION). The names mysql,
 * innodb_system and innodb_temporary, and the names starting with innodb_, are reserved; the
 * server has those tablespaces, and the undo tablespaces innodb_undo_001 and innodb_undo_002.
 * Names compare with regard to letter case. A data file name must end with .ibd (.ibu for an
 * undo tablespace) and may not repeat the file of another tablespace; ENCRYPTION 'Y' needs a
 * keyring the server lacks, and ALTER TABLESPACE checks the keyring before the value; AUTOEXTEND_SIZE
 * is 0 or a multiple of 4M from 4M to 4096M. A refused option is followed by the failure of the
 * statement and an error of the engine. ALTER and
 * DROP TABLESPACE refuse an ENGINE option on an existing tablespace with a syntax error, and an
 * active undo tablespace is not empty (verified on a live 8.4 server). The emulator does not
 * track which tables use a tablespace, so DROP TABLESPACE never finds one in use.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-tablespace.html,
 * https://dev.mysql.com/doc/refman/8.4/en/alter-tablespace.html,
 * https://dev.mysql.com/doc/refman/8.4/en/drop-tablespace.html,
 * https://dev.mysql.com/doc/refman/8.4/en/innodb-undo-tablespaces.html.
 *
 * @visibility MySqlMemory
 */
final class TablespaceCommand implements Command
{
    /**
     * The tablespaces of a newly installed server, with whether each is an undo tablespace.
     */
    public const SYSTEM = ['innodb_system' => false, 'innodb_temporary' => false, 'mysql' => false, 'innodb_undo_001' => true, 'innodb_undo_002' => true];

    /**
     * Answers true.
     */
    #[Override]
    public function clearsDiagnostics(): bool
    {
        return true;
    }

    /**
     * Creates, changes or drops the tablespace.
     */
    #[Override]
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        $statement = $operation->statement;
        $registry = $session->instance->registry;
        $session->transaction->commit();
        match (true) {
            $statement instanceof CreateTablespace => $this->create($statement, $registry),
            $statement instanceof CreateUndoTablespace => $this->createUndo($statement, $registry),
            $statement instanceof RenameTablespace => $this->rename($statement, $registry),
            $statement instanceof AlterTablespace => $this->alter($statement->name->value, $statement->options, $registry),
            $statement instanceof AlterTablespaceDatafile => $this->datafile($statement, $registry),
            $statement instanceof AlterTablespaceAccess => throw StatementError::SyntaxError->error(),
            $statement instanceof AlterUndoTablespace => $this->activate($statement, $registry),
            $statement instanceof DropTablespace => $this->drop($statement, $registry),
            $statement instanceof DropUndoTablespace => $this->dropUndo($statement, $registry),
            default => throw StatementError::NotSupportedYet->error('this tablespace statement'),
        };

        return new Completion();
    }

    /**
     * Creates a general tablespace.
     *
     * @throws SqlError When the engine, the name, the file or an option is refused
     */
    public function create(CreateTablespace $statement, Registry $registry): void
    {
        $name = $statement->name->value;
        $this->engine($statement->options, 'CREATE TABLESPACE');
        $this->reserved($name);
        if ($this->exists($name, $registry)) {
            throw SchemaError::TablespaceExists->error($name);
        }
        $failed = 'TABLESPACE ' . $name;
        $file = $statement->datafile === null ? '' : (new Literals())->bytes($statement->datafile);
        if ($statement->datafile !== null) {
            $this->file($file, '.ibd', $failed, $name, $registry);
        }
        foreach ($statement->options as $option) {
            if ($option instanceof SizeOption && $option->kind === SizeOptionKind::FileBlock && !in_array($this->size($option), ['1024', '2048', '4096', '8192', '16384'], true)) {
                throw new SqlError(SchemaError::IllegalCreateOption, 'InnoDB does not support FILE_BLOCK_SIZE=' . $this->size($option), null, [[SchemaError::CreateFilegroupFailed->value, SchemaError::CreateFilegroupFailed->message($failed)], [SchemaError::IllegalHa->value, SchemaError::IllegalHa->message($name)]]);
            }
        }
        $this->options($statement->options, [SchemaError::CreateFilegroupFailed->value, SchemaError::CreateFilegroupFailed->message($failed)], $name, true);
        $registry->tablespaces[$name] = new Tablespace($name, false, $file);
    }

    /**
     * Creates an undo tablespace.
     *
     * @throws SqlError When the engine, the name or the file is refused
     */
    public function createUndo(CreateUndoTablespace $statement, Registry $registry): void
    {
        $name = $statement->name->value;
        $this->engine($statement->options, 'CREATE UNDO TABLESPACE');
        $this->reserved($name);
        if ($this->exists($name, $registry)) {
            throw SchemaError::TablespaceExists->error($name);
        }
        $file = (new Literals())->bytes($statement->datafile);
        $this->file($file, '.ibu', 'UNDO TABLESPACE ' . $name, $name, $registry);
        $registry->tablespaces[$name] = new Tablespace($name, true, $file);
    }

    /**
     * Renames a general tablespace.
     *
     * @throws SqlError When either name is reserved, the tablespace is missing or the new name is taken
     */
    public function rename(RenameTablespace $statement, Registry $registry): void
    {
        $name = $statement->name->value;
        $target = $statement->target->value;
        $this->reserved($name);
        $tablespace = $this->find($name, $registry);
        if ($this->exists($target, $registry)) {
            throw SchemaError::TablespaceExists->error($target);
        }
        $this->reserved($target);
        unset($registry->tablespaces[$name]);
        $tablespace->name = $target;
        $registry->tablespaces[$target] = $tablespace;
    }

    /**
     * Changes the options of a general tablespace.
     *
     * @param list<StorageOption> $options
     *
     * @throws SqlError When the name is reserved, the tablespace is missing or an option is refused
     */
    public function alter(string $name, array $options, Registry $registry): void
    {
        $this->reserved($name);
        $this->find($name, $registry);
        foreach ($options as $option) {
            if ($option instanceof EngineOption) {
                throw StatementError::SyntaxError->error();
            }
        }
        $this->options($options, [SchemaError::AlterFilegroupFailed->value, SchemaError::AlterFilegroupFailed->message('TABLESPACE ' . $name)], $name, false);
    }

    /**
     * Refuses to add or drop a data file: an InnoDB tablespace has one file, and CHANGE DATAFILE is not InnoDB syntax.
     *
     * @throws SqlError Always
     */
    public function datafile(AlterTablespaceDatafile $statement, Registry $registry): void
    {
        $name = $statement->name->value;
        $this->reserved($name);
        $tablespace = $this->find($name, $registry);
        $file = (new Literals())->bytes($statement->datafile);
        if ($statement->action === DatafileAction::Drop && $file !== $tablespace->file) {
            throw SchemaError::TablespaceFileMissing->error($name, $file);
        }
        if ($statement->action === DatafileAction::Change) {
            throw StatementError::SyntaxError->error();
        }
        $operation = 'ALTER TABLESPACE ... ' . $statement->action->value . ' DATAFILE';

        throw new SqlError(SchemaError::AlterFilegroupFailed, SchemaError::AlterFilegroupFailed->message('TABLESPACE ' . $name), null, [[SchemaError::EngineUnsupportedOperation->value, SchemaError::EngineUnsupportedOperation->message($operation)]]);
    }

    /**
     * Makes an undo tablespace active or inactive, once its ENGINE option names InnoDB.
     *
     * @throws SqlError When the engine is refused, or the tablespace is missing or a general one
     */
    public function activate(AlterUndoTablespace $statement, Registry $registry): void
    {
        $name = $statement->name->value;
        $this->engine($statement->options, 'ALTER UNDO TABLESPACE');
        if (isset(self::SYSTEM[$name]) && self::SYSTEM[$name]) {
            return;
        }
        $tablespace = $this->find($name, $registry);
        if (!$tablespace->undo) {
            throw new SqlError(SchemaError::WrongTablespaceName, 'Cannot ALTER UNDO TABLESPACE `' . $name . '` because it is a general tablespace.  Please use ALTER TABLESPACE.', null, [[SchemaError::AlterFilegroupFailed->value, SchemaError::AlterFilegroupFailed->message('UNDO TABLESPACE ' . $name)], [AdministrationError::OperationDisallowed->value, AdministrationError::OperationDisallowed->message('ALTER UNDO TABLEPSPACE', $name)]]);
        }
        $tablespace->active = $statement->active;
    }

    /**
     * Drops a general tablespace.
     *
     * @throws SqlError When the name is reserved, the tablespace is missing or an undo one, or an ENGINE option is written
     */
    public function drop(DropTablespace $statement, Registry $registry): void
    {
        $name = $statement->name->value;
        $this->reserved($name);
        $tablespace = $this->find($name, $registry);
        foreach ($statement->options as $option) {
            if ($option instanceof EngineOption) {
                throw StatementError::SyntaxError->error();
            }
        }
        if ($tablespace->undo) {
            throw new SqlError(SchemaError::WrongTablespaceName, 'Cannot DROP TABLESPACE `' . $name . '` because it is an undo tablespace.  Please use DROP UNDO TABLESPACE.', null, [[SchemaError::DropFilegroupFailed->value, SchemaError::DropFilegroupFailed->message('TABLESPACE ' . $name)], [AdministrationError::OperationDisallowed->value, AdministrationError::OperationDisallowed->message('DROP TABLEPSPACE', $name)]]);
        }
        unset($registry->tablespaces[$name]);
    }

    /**
     * Drops an inactive undo tablespace.
     *
     * @throws SqlError When the engine or the name is refused, the tablespace is missing, a general one or active
     */
    public function dropUndo(DropUndoTablespace $statement, Registry $registry): void
    {
        $name = $statement->name->value;
        $this->engine($statement->options, 'DROP UNDO TABLESPACE');
        $this->reserved($name);
        $tablespace = $this->find($name, $registry);
        if (!$tablespace->undo) {
            throw new SqlError(SchemaError::WrongTablespaceName, 'Cannot DROP UNDO TABLESPACE `' . $name . '` because it is a general tablespace.  Please use DROP TABLESPACE.', null, [[SchemaError::DropFilegroupFailed->value, SchemaError::DropFilegroupFailed->message('UNDO TABLESPACE ' . $name)], [AdministrationError::OperationDisallowed->value, AdministrationError::OperationDisallowed->message('DROP UNDO TABLEPSPACE', $name)]]);
        }
        if ($tablespace->active) {
            throw new SqlError(SchemaError::DropFilegroupFailed, SchemaError::DropFilegroupFailed->message('UNDO TABLESPACE ' . $name), null, [[SchemaError::TablespaceNotEmpty->value, SchemaError::TablespaceNotEmpty->message($name)]]);
        }
        unset($registry->tablespaces[$name]);
    }

    /**
     * Checks the ENGINE option: the last one written must name an enabled engine, and that engine must be InnoDB.
     *
     * @param list<StorageOption> $options
     *
     * @throws SqlError When the engine is unknown or not InnoDB
     */
    public function engine(array $options, string $operation): void
    {
        $named = null;
        foreach ($options as $option) {
            if ($option instanceof EngineOption) {
                $named = $option->engine->value;
            }
        }
        if ($named === null) {
            return;
        }
        $engine = ServerCatalog::shared()->engine($named) ?? throw SchemaError::UnknownStorageEngine->error($named);
        if ($engine !== 'InnoDB') {
            throw SchemaError::IllegalCreateOption->error($engine, $operation);
        }
    }

    /**
     * Refuses a reserved tablespace name.
     *
     * @throws SqlError When the name is reserved or empty
     */
    public function reserved(string $name): void
    {
        $reason = match (true) {
            $name === '' => null,
            in_array($name, ['innodb_system', 'innodb_temporary', 'mysql'], true) => 'InnoDB: `' . $name . '` is a reserved tablespace name.',
            str_starts_with($name, 'innodb_') => 'InnoDB: Tablespace names starting with `innodb_` are reserved.',
            default => false,
        };
        if ($reason === false) {
            return;
        }
        $incorrect = SchemaError::WrongTablespaceName->message($name);

        throw new SqlError(SchemaError::WrongTablespaceName, $reason ?? $incorrect, null, [[SchemaError::WrongTablespaceName->value, $incorrect]]);
    }

    /**
     * Tells whether a tablespace of the name exists.
     */
    public function exists(string $name, Registry $registry): bool
    {
        return isset(self::SYSTEM[$name]) || isset($registry->tablespaces[$name]);
    }

    /**
     * Finds a tablespace a statement created.
     *
     * @throws SqlError When there is none of the name
     */
    public function find(string $name, Registry $registry): Tablespace
    {
        return $registry->tablespaces[$name] ?? throw SchemaError::TablespaceMissing->error($name);
    }

    /**
     * Checks the name of a data file.
     *
     * @throws SqlError When the name lacks the extension, names a directory or repeats the file of another tablespace
     */
    public function file(string $file, string $extension, string $failed, string $name, Registry $registry): void
    {
        $base = basename($file);
        $reasons = [];
        if (str_starts_with($file, '/')) {
            $reasons[] = 'The DATAFILE location must be in a known directory.';
        } elseif (str_contains($file, '/')) {
            $reasons = ['The directory does not exist or is incorrect.', 'The DATAFILE location cannot be under the datadir.'];
        } elseif (!str_ends_with($base, $extension) || $base === $extension) {
            $reasons[] = 'The ADD DATAFILE filepath does not have a proper filename.';
            if (!str_ends_with($base, $extension)) {
                $reasons[] = "The ADD DATAFILE filepath must end with '" . $extension . "'.";
            }
        }
        if ($reasons !== []) {
            $following = array_map(static fn (string $reason): array => [SchemaError::WrongFileName->value, $reason], array_slice($reasons, 1));
            $following[] = [SchemaError::CreateFilegroupFailed->value, SchemaError::CreateFilegroupFailed->message($failed)];
            $following[] = [SchemaError::WrongFileName->value, SchemaError::WrongFileName->message($file)];

            throw new SqlError(SchemaError::WrongFileName, $reasons[0], null, $following);
        }
        foreach ($registry->tablespaces as $tablespace) {
            if ($tablespace->file === $file) {
                throw SchemaError::DuplicateTablespaceFile->error($name);
            }
        }
    }

    /**
     * Checks the ENCRYPTION and AUTOEXTEND_SIZE options.
     *
     * @param list<StorageOption> $options
     * @param array{int, string} $failed The error that follows a refused option
     *
     * @throws SqlError When an option is refused
     */
    public function options(array $options, array $failed, string $name, bool $creating): void
    {
        foreach ($options as $option) {
            if ($option instanceof EncryptionOption) {
                $value = strtoupper((new Literals())->bytes($option->encryption));
                $engine = $creating ? [AdministrationError::GetErrno->value, AdministrationError::GetErrno->message('138', 'Unsupported extension used for table')] : [AdministrationError::GetErrno->value, AdministrationError::GetErrno->message('168', 'Unknown (generic) error from engine')];
                if ($creating && !in_array($value, ['Y', 'N'], true)) {
                    throw new SqlError(SchemaError::InvalidEncryption, SchemaError::InvalidEncryption->message(), null, [$failed, $engine]);
                }
                if ($value === 'Y' || !$creating) {
                    throw new SqlError(AdministrationError::KeyringMissing, AdministrationError::KeyringMissing->message(), null, [$failed, $engine]);
                }
            }
            if ($option instanceof SizeOption && $option->kind === SizeOptionKind::Autoextend) {
                $size = $this->size($option);
                $mega = '4194304';
                $code = match (true) {
                    $size !== '0' && (bccomp($size, $mega) < 0 || bccomp($size, bcmul($mega, '1024')) > 0) => SchemaError::AutoextendSize,
                    bcmod($size, $mega) !== '0' => SchemaError::AutoextendMultiple,
                    default => null,
                };
                if ($code !== null) {
                    $engine = $creating ? [SchemaError::IllegalHa->value, SchemaError::IllegalHa->message($name)] : [AdministrationError::GetErrno->value, AdministrationError::GetErrno->message((string) $code->value, 'Unknown error ' . $code->value)];

                    throw new SqlError($code, $code === SchemaError::AutoextendMultiple ? $code->message('4M') : $code->message(), null, [$failed, $engine]);
                }
            }
        }
    }

    /**
     * Answers the bytes of a size option: a number, or a number followed by K, M or G.
     *
     * @return numeric-string
     */
    public function size(SizeOption $option): string
    {
        $size = $option->size;
        if ($size->number !== null) {
            return (new Literals())->number($size->number);
        }
        $word = $size->word->value ?? '0';
        if (preg_match('/\A([0-9]+)([KMGkmg])\z/', $word, $match) !== 1) {
            return '0';
        }

        return bcmul($match[1], match (strtoupper($match[2])) {
            'K' => '1024',
            'M' => '1048576',
            'G' => '1073741824',
        });
    }
}
