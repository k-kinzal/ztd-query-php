<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Admin\Storage;

use MySqlMemory\Command\Admin\TablespaceCommand;
use MySqlMemory\Error\Family\SchemaError;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\SizeOption;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\SizeOptionKind;
use SqlSemantics\Platform\MySql\Statement\Server\Storage\Option\StorageOption;

/**
 * Interprets the last tablespace extension setting in bytes and native pages.
 *
 * The range check uses bytes; the multiple check uses complete 16 KiB pages.
 * Observed with CREATE and ALTER TABLESPACE on native MySQL servers, including
 * values immediately before and after a page boundary and repeated settings.
 *
 * @visibility MySqlMemory
 */
final class Autoextend
{
    /**
     * @param list<StorageOption> $options The settings in written order
     * @return SchemaError|null The refused range or page multiple, if any
     */
    public function problem(array $options): ?SchemaError
    {
        $size = '0';
        foreach ($options as $option) {
            if ($option instanceof SizeOption && $option->kind === SizeOptionKind::Autoextend) {
                $size = (new TablespaceCommand())->size($option);
            }
        }

        return match (true) {
            $size !== '0' && (bccomp($size, '4194304') < 0 || bccomp($size, '4294967296') > 0) => SchemaError::AutoextendSize,
            bcmod(bcdiv($size, '16384', 0), '256') !== '0' => SchemaError::AutoextendMultiple,
            default => null,
        };
    }
}
