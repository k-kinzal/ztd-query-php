<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Alter\Partition;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Contract\NameUse;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Resolution\Environment;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\QualifiedName;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Rules\TableChange\Targets;
use SqlSemantics\Platform\MySql\Statement\Alter\Modifier\ValidationOption;

/**
 * `EXCHANGE PARTITION p WITH TABLE t [WITH | WITHOUT VALIDATION]`: a request to swap a partition with an unpartitioned table.
 *
 * Mirrors PT_alter_table_exchange_partition. The other table resolves by
 * MYSQL-CHANGE-TARGET-001 and its resolution is the relation fact of this
 * node. The validation option exists from 5.7 on.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/partitioning-management-exchange.html.
 *
 * @visibility SqlSemantics
 */
final class ExchangePartition implements StandaloneCommand
{
    use Snapshot;

    /**
     * @param Name $partition The partition
     * @param QualifiedName $table The table to swap with
     * @param ValidationOption|null $validation WITH or WITHOUT VALIDATION, when written
     */
    public function __construct(public readonly Name $partition, public readonly QualifiedName $table, public readonly ?ValidationOption $validation = null)
    {
        Check::input($table->catalog === null, 'A table is qualified by at most a database.');
    }

    /**
     * Records the resolution of the other table.
     */
    public function deriveCommand(Derivation $derivation, Environment $scope): void
    {
        $derivation->target($this, (new Targets())->target($derivation, $this->table));
    }

    /**
     * Writes the action.
     */
    public function render(Output $out): void
    {
        $out->keyword('EXCHANGE', 'PARTITION')->name($this->partition, NameUse::Label)->keyword('WITH', 'TABLE');
        if ($this->table->schema !== null) {
            $out->name($this->table->schema, NameUse::Qualifier)->symbol('.');
        }
        $out->name($this->table->name, NameUse::Relation)->node($this->validation);
    }
}
