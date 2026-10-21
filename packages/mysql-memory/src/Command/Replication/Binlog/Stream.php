<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Replication\Binlog;

/**
 * Connection-local binary event layout and table identities between statements.
 * This runtime state never belongs to a retained SQL semantic value.
 *
 * @visibility MySqlMemory
 */
final class Stream
{
    /**
     * @param Format|null $format The latest format description, or none yet
     * @param array<int, TableMap> $tables Source tables until a statement-end flag
     */
    public function __construct(public ?Format $format = null, public array $tables = [])
    {
    }
}
