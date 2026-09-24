<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition\MySqlObject;

use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Storage\LogfileGroupOptions;
use SqlSemantics\Model\Definition\Storage\StorageEncryption;
use SqlSemantics\Model\Definition\Storage\TablespaceChanges;
use SqlSemantics\Model\Definition\Storage\TablespaceOptions;
use SqlSemantics\Model\Sql\Atom;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Literal;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Definition\MySql\Storage as Statement;

/**
 * Writes tablespace, undo tablespace, and logfile group definitions from their operands.
 * @visibility SqlSemantics
 */
final class StorageDefinitions
{
    /**
     * Returns null for statements outside the storage definitions.
     */
    public static function write(BoundStatement $statement): ?Tree
    {
        return match (true) {
            $statement instanceof Statement\CreateTablespaceStatement => new Tree('create-tablespace', [
                Build::keyword('CREATE TABLESPACE'), self::name($statement->name),
                ...($statement->datafile === null ? [] : [Build::keyword('ADD DATAFILE'), self::text($statement->datafile)]),
                ...($statement->logfileGroup === null ? [] : [Build::keyword('USE LOGFILE GROUP'), self::name($statement->logfileGroup)]),
                ...self::tablespace($statement->options),
            ]),
            $statement instanceof Statement\AddTablespaceDatafileStatement => new Tree('alter-tablespace', [Build::keyword('ALTER TABLESPACE'), self::name($statement->name), Build::keyword('ADD DATAFILE'), self::text($statement->datafile), ...self::changes($statement->changes)]),
            $statement instanceof Statement\DropTablespaceDatafileStatement => new Tree('alter-tablespace', [Build::keyword('ALTER TABLESPACE'), self::name($statement->name), Build::keyword('DROP DATAFILE'), self::text($statement->datafile), ...self::changes($statement->changes)]),
            $statement instanceof Statement\ChangeTablespaceDatafileStatement => new Tree('alter-tablespace', [Build::keyword('ALTER TABLESPACE'), self::name($statement->name), Build::keyword('CHANGE DATAFILE'), self::text($statement->datafile), ...self::sizes(['INITIAL_SIZE' => $statement->initialSize, 'AUTOEXTEND_SIZE' => $statement->autoextendSize, 'MAX_SIZE' => $statement->maxSize])]),
            $statement instanceof Statement\AlterTablespaceStatement => new Tree('alter-tablespace', [Build::keyword('ALTER TABLESPACE'), self::name($statement->name), ...self::changes($statement->changes)]),
            $statement instanceof Statement\RenameTablespaceStatement => new Tree('alter-tablespace', [Build::keyword('ALTER TABLESPACE'), self::name($statement->name), Build::keyword('RENAME TO'), self::name($statement->newName)]),
            $statement instanceof Statement\SetTablespaceAccessStatement => new Tree('alter-tablespace', [Build::keyword('ALTER TABLESPACE'), self::name($statement->name), Build::keyword($statement->access->value)]),
            $statement instanceof Statement\CreateUndoTablespaceStatement => new Tree('create-undo-tablespace', [Build::keyword('CREATE UNDO TABLESPACE'), self::name($statement->name), Build::keyword('ADD DATAFILE'), self::text($statement->datafile), ...self::engine($statement->engine)]),
            $statement instanceof Statement\AlterUndoTablespaceStatement => new Tree('alter-undo-tablespace', [Build::keyword('ALTER UNDO TABLESPACE'), self::name($statement->name), Build::keyword('SET ' . $statement->state->value), ...self::engine($statement->engine)]),
            $statement instanceof Statement\CreateLogfileGroupStatement => new Tree('create-logfile-group', [Build::keyword('CREATE LOGFILE GROUP'), self::name($statement->name), Build::keyword('ADD ' . $statement->fileKind->value), self::text($statement->file), ...self::logfileGroup($statement->options)]),
            $statement instanceof Statement\AlterLogfileGroupStatement => new Tree('alter-logfile-group', [
                Build::keyword('ALTER LOGFILE GROUP'), self::name($statement->name), Build::keyword('ADD ' . $statement->fileKind->value), self::text($statement->file),
                ...self::sizes(['INITIAL_SIZE' => $statement->initialSize]), ...self::engine($statement->engine), Build::keyword($statement->waiting->value),
            ]),
            default => null,
        };
    }

    /**
     * Writes the initial tablespace properties in a fixed order.
     * @return list<Tree>
     */
    public static function tablespace(TablespaceOptions $options): array
    {
        return [
            ...self::sizes(['INITIAL_SIZE' => $options->initialSize, 'AUTOEXTEND_SIZE' => $options->autoextendSize, 'MAX_SIZE' => $options->maxSize, 'EXTENT_SIZE' => $options->extentSize, 'FILE_BLOCK_SIZE' => $options->fileBlockSize, 'NODEGROUP' => $options->nodegroup]),
            ...self::engine($options->engine),
            ...self::texts(['COMMENT' => $options->comment, 'ENGINE_ATTRIBUTE' => $options->engineAttribute], $options->encryption),
            Build::keyword($options->waiting->value),
        ];
    }

    /**
     * Writes the tablespace property changes, always ending with the completion request.
     * @return list<Tree>
     */
    public static function changes(TablespaceChanges $changes): array
    {
        return [
            ...self::sizes(['INITIAL_SIZE' => $changes->initialSize, 'AUTOEXTEND_SIZE' => $changes->autoextendSize, 'MAX_SIZE' => $changes->maxSize]),
            ...self::engine($changes->engine),
            ...self::texts(['ENGINE_ATTRIBUTE' => $changes->engineAttribute], $changes->encryption),
            Build::keyword($changes->waiting->value),
        ];
    }

    /**
     * Writes the initial logfile group properties.
     * @return list<Tree>
     */
    public static function logfileGroup(LogfileGroupOptions $options): array
    {
        return [
            ...self::sizes(['INITIAL_SIZE' => $options->initialSize, 'UNDO_BUFFER_SIZE' => $options->undoBufferSize, 'REDO_BUFFER_SIZE' => $options->redoBufferSize, 'NODEGROUP' => $options->nodegroup]),
            ...self::engine($options->engine),
            ...self::texts(['COMMENT' => $options->comment], null),
            Build::keyword($options->waiting->value),
        ];
    }

    /**
     * @param array<string, ?int> $sizes Byte counts and node groups keyed by option keyword
     * @return list<Tree>
     */
    public static function sizes(array $sizes): array
    {
        $parts = [];
        foreach ($sizes as $keyword => $size) {
            if ($size !== null) {
                $parts[] = Build::keyword($keyword . ' = ' . $size);
            }
        }
        return $parts;
    }

    /**
     * @param array<string, ?string> $texts String options keyed by option keyword
     * @return list<Tree>
     */
    public static function texts(array $texts, ?StorageEncryption $encryption): array
    {
        $parts = [];
        foreach ([...$texts, 'ENCRYPTION' => $encryption?->value] as $keyword => $text) {
            if ($text !== null) {
                array_push($parts, Build::keyword($keyword . ' ='), self::text($text));
            }
        }
        return $parts;
    }

    /**
     * @return list<Tree>
     */
    public static function engine(?string $engine): array
    {
        return $engine === null ? [] : [Build::keyword('ENGINE ='), self::name($engine)];
    }

    /**
     * Quotes a storage object name as a MySQL identifier.
     */
    public static function name(string $name): Tree
    {
        return Build::identifier([$name], Dialect::MySql);
    }

    /**
     * Writes a MySQL string literal.
     */
    public static function text(string $text): Tree
    {
        return new Tree('text', [new Atom('literal', Literal::encode($text, Dialect::MySql)[0])]);
    }
}
