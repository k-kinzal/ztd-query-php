<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `ALTER RESOURCE GROUP name [VCPU = …] [THREAD_PRIORITY = n] [ENABLE|DISABLE] [FORCE]` (8.0+).
 *
 * Mirrors PT_alter_resource_group. Rule: MYSQL-RESOURCE-GROUP-001. The type
 * of the group is not written, and groups are not declared in a context, so
 * the priority range cannot be checked.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-resource-group.html. Status: Implemented.
 *
 * @visibility public
 * @example Disabling a group by force
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('alter resource group batch disable force')->toString() // => 'ALTER RESOURCE GROUP batch DISABLE FORCE'
 */
final class AlterResourceGroup implements Statement
{
    use Snapshot;

    /**
     * @var list<CpuRange> The CPUs of VCPU; none when the option is absent
     */
    public readonly array $cpus;

    /**
     * @param Name $name The group name
     * @param list<CpuRange> $cpus The CPUs of VCPU; none when the option is absent
     * @param ThreadPriority|null $priority The THREAD_PRIORITY, when written
     * @param bool|null $enabled ENABLE (true) or DISABLE (false), when written
     * @param bool $force Whether FORCE is written
     */
    public function __construct(public readonly Name $name, array $cpus = [], public readonly ?ThreadPriority $priority = null, public readonly ?bool $enabled = null, public readonly bool $force = false)
    {
        $this->cpus = Check::listOf($cpus, CpuRange::class, 'VCPU lists CPU numbers and ranges.');
    }

    /**
     * Derives nothing: the statement names no relation and no expression.
     */
    public function deriveStatement(Derivation $derivation): void
    {
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALTER', 'RESOURCE', 'GROUP')->name($this->name, NameUse::Label);
        if ($this->cpus !== []) {
            $out->keyword('VCPU')->symbol('=')->list($this->cpus);
        }
        if ($this->priority !== null) {
            $out->keyword('THREAD_PRIORITY')->symbol('=')->node($this->priority);
        }
        if ($this->enabled !== null) {
            $out->keyword($this->enabled ? 'ENABLE' : 'DISABLE');
        }
        if ($this->force) {
            $out->keyword('FORCE');
        }
    }
}
