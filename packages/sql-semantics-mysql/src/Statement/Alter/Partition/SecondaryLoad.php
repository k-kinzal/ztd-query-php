<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Alter\Partition;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * `SECONDARY_LOAD` or `SECONDARY_UNLOAD` (8.0 and later), with `PARTITION (p, …)` from 9.0 on: a request to load the table into or unload it from its secondary engine.
 *
 * Mirrors PT_alter_table_secondary_load and PT_alter_table_secondary_unload.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-table.html.
 *
 * @visibility public
 * @example Loading a table into the secondary engine
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('ALTER TABLE t SECONDARY_LOAD')->statement->commands[0]->load // => true
 */
final class SecondaryLoad implements StandaloneCommand
{
    use Snapshot;

    /**
     * @var list<Name> The partitions in order; empty for the whole table
     */
    public readonly array $partitions;

    /**
     * @param bool $load Whether SECONDARY_LOAD (true) or SECONDARY_UNLOAD (false) is written
     * @param list<Name> $partitions The partitions in order; empty for the whole table
     */
    public function __construct(public readonly bool $load, array $partitions = [])
    {
        $this->partitions = Check::listOf($partitions, Name::class, 'The partitions of a secondary load are a list of names.');
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
        $out->keyword($this->load ? 'SECONDARY_LOAD' : 'SECONDARY_UNLOAD');
        if ($this->partitions !== []) {
            $out->keyword('PARTITION')->symbol('(');
            foreach ($this->partitions as $index => $partition) {
                if ($index > 0) {
                    $out->symbol(',');
                }
                $out->name($partition, NameUse::Label);
            }
            $out->symbol(')');
        }
    }
}
