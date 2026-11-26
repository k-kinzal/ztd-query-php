<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Contract\NameUse;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Model\Statement\Statement;
use SqlSemantics\Platform\MySql\Rules\Account\NumberChecks;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\PriorityOutOfRange;

/**
 * `CREATE RESOURCE GROUP name TYPE = USER|SYSTEM [VCPU = …] [THREAD_PRIORITY = n] [ENABLE|DISABLE]` (8.0+).
 *
 * Mirrors PT_create_resource_group. Rule: MYSQL-RESOURCE-GROUP-001. A
 * priority outside -20 to 0 for a system group or 0 to 19 for a user group
 * is PriorityOutOfRange. The `=` signs are optional words.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-resource-group.html. Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class CreateResourceGroup implements Statement
{
    use Snapshot;

    /**
     * @var list<CpuRange> The CPUs of VCPU; none when the option is absent
     */
    public readonly array $cpus;

    /**
     * @param Name $name The group name
     * @param ResourceGroupKind $kind The group type
     * @param list<CpuRange> $cpus The CPUs of VCPU; none when the option is absent
     * @param ThreadPriority|null $priority The THREAD_PRIORITY, when written
     * @param bool|null $enabled ENABLE (true) or DISABLE (false), when written
     */
    public function __construct(public readonly Name $name, public readonly ResourceGroupKind $kind, array $cpus = [], public readonly ?ThreadPriority $priority = null, public readonly ?bool $enabled = null)
    {
        $this->cpus = Check::listOf($cpus, CpuRange::class, 'VCPU lists CPU numbers and ranges.');
    }

    /**
     * Reports a priority outside the range of the group's type.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        if ($this->priority === null) {
            return;
        }
        $value = (new NumberChecks())->value($this->priority->number);
        $zero = $value === '0';
        $outside = $this->kind === ResourceGroupKind::System
            ? !$zero && (!$this->priority->negative || $value === null || (new NumberChecks())->compare($value, '20') > 0)
            : !$zero && ($this->priority->negative || $value === null || (new NumberChecks())->compare($value, '19') > 0);
        if ($outside) {
            $derivation->report(new PriorityOutOfRange($this->kind->value, $this->priority->describe()));
        }
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('CREATE', 'RESOURCE', 'GROUP')->name($this->name, NameUse::Label)->keyword('TYPE')->symbol('=')->keyword($this->kind->value);
        if ($this->cpus !== []) {
            $out->keyword('VCPU')->symbol('=')->list($this->cpus);
        }
        if ($this->priority !== null) {
            $out->keyword('THREAD_PRIORITY')->symbol('=')->node($this->priority);
        }
        if ($this->enabled !== null) {
            $out->keyword($this->enabled ? 'ENABLE' : 'DISABLE');
        }
    }
}
