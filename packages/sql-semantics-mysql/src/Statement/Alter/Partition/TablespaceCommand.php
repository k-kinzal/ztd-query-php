<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Alter\Partition;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\MySql\Statement\Partition\PartitionSelection;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Snapshot;

/**
 * `DISCARD | IMPORT [PARTITION ALL | p, …] TABLESPACE`: a request to detach or attach the tablespace files of a table or of partitions.
 *
 * Mirrors PT_alter_table_discard_tablespace, …_import_tablespace,
 * …_discard_partition_tablespace and …_import_partition_tablespace.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/innodb-table-import.html.
 *
 * @visibility public
 * @example Discarding the tablespace of a table
 *     $alter = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('ALTER TABLE t DISCARD TABLESPACE');
 *     [$alter->statement->commands[0]->import, $alter->toString()] // => [false, 'ALTER TABLE t DISCARD TABLESPACE']
 */
final class TablespaceCommand implements StandaloneCommand
{
    use Snapshot;

    /**
     * @param bool $import Whether IMPORT (true) or DISCARD (false) is written
     * @param PartitionSelection|null $partitions The partitions, or null for the whole table
     */
    public function __construct(public readonly bool $import, public readonly ?PartitionSelection $partitions = null)
    {
    }

    /**
     * Derives nothing: the action holds no expression.
     */
    public function deriveCommand(Derivation $derivation, Environment $scope): void
    {
    }

    /**
     * Writes the action.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->import ? 'IMPORT' : 'DISCARD');
        if ($this->partitions !== null) {
            $out->keyword('PARTITION')->node($this->partitions);
        }
        $out->keyword('TABLESPACE');
    }
}
