<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Admin\Storage;

use MySqlMemory\Command\Admin\TablespaceCommand;
use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Registry\Registry;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\CreateTablespace;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\SizeOption;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\SizeOptionKind;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\StorageOption;

/**
 * Preserves tablespace block-size and filename conditions in native order.
 *
 * Zero selects the default page size. Modern duplicate-file lookup precedes
 * block-size validation; legacy creation reports a refused block size first.
 *
 * @visibility MySqlMemory
 */
final class CreationOptions
{
    /**
     * Checks all creation conditions before registering a datafile.
     *
     * @throws SqlError When a size or filename is refused
     */
    public function check(CreateTablespace $statement, Registry $registry, bool $legacy, string $file): void
    {
        $problem = $this->block($statement->options);
        $extension = $legacy ? null : (new Autoextend())->problem($statement->options);
        $name = $statement->name->value;
        $failure = null;
        try {
            if ($statement->datafile !== null) {
                (new TablespaceCommand())->file($file, '.ibd', 'TABLESPACE ' . $name, $name, $registry);
            }
        } catch (SqlError $error) {
            if ($error->error === SchemaError::DuplicateTablespaceFile && (!$legacy || $problem === null)) {
                throw $error;
            }
            if ($error->error !== SchemaError::DuplicateTablespaceFile) {
                $failure = $legacy ? $this->legacy($error, $file, $name) : $error;
            }
        }
        if ($problem !== null || $extension !== null) {
            $conditions = $problem === null ? [] : [[SchemaError::IllegalCreateOption->value, $problem]];
            if ($extension !== null) {
                $conditions[] = [$extension->value, $extension === SchemaError::AutoextendMultiple ? $extension->message('4M') : $extension->message()];
            }
            $first = array_shift($conditions);
            $following = $failure === null ? [[SchemaError::CreateFilegroupFailed->value, SchemaError::CreateFilegroupFailed->message('TABLESPACE ' . $name)], [SchemaError::IllegalHa->value, SchemaError::IllegalHa->message($name)]] : [[$failure->getCode(), $failure->getMessage()], ...$failure->following];
            throw new SqlError(SchemaError::from($first[0]), $first[1], $failure, [...$conditions, ...$following]);
        }
        if ($failure !== null) {
            throw $failure;
        }
    }

    /**
     * @param list<StorageOption> $options The complete written options
     * @return string|null The first refused block-size condition
     */
    public function block(array $options): ?string
    {
        foreach ($options as $option) {
            if ($option instanceof SizeOption && $option->kind === SizeOptionKind::FileBlock) {
                $size = (new TablespaceCommand())->size($option);
                if (in_array($size, ['0', '1024', '2048', '4096', '8192', '16384'], true)) {
                    continue;
                }
                return in_array($size, ['32768', '65536'], true) ? 'InnoDB: Cannot create a tablespace with FILE_BLOCK_SIZE=' . $size . ' because INNODB_PAGE_SIZE=16384.' : 'InnoDB does not support FILE_BLOCK_SIZE=' . $size;
            }
        }
        return null;
    }

    /**
     * Retains the legacy filename condition followed by creation and engine failures.
     */
    public function legacy(SqlError $error, string $file, string $name): SqlError
    {
        if ($error->error !== SchemaError::WrongFileName) {
            return $error;
        }
        $path = (new DatafileChecks())->path($file);
        $base = basename($path);
        $following = strlen($base) > 4 && !str_ends_with($base, '.ibd') ? [[SchemaError::WrongFileName->value, 'An IBD filepath must end with `.ibd`.']] : [];
        return new SqlError(SchemaError::WrongFileName, SchemaError::WrongFileName->message(explode("\0", $file, 2)[0]), $error, [
            ...$following,
            [SchemaError::CreateFilegroupFailed->value, SchemaError::CreateFilegroupFailed->message('TABLESPACE ' . $name)],
            [SchemaError::IllegalHa->value, SchemaError::IllegalHa->message($name)],
        ]);
    }
}
