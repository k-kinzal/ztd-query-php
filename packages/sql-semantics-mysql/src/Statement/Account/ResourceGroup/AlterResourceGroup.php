<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Rendering\Output;

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
final class AlterResourceGroup extends \SqlSemantics\Platform\MySql\Statement\SqlStatement implements Statement, \SqlSemantics\Statement\AnalyzedStatement
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @var list<CpuRange> The CPUs of VCPU; none when the option is absent
     */
    public readonly array $cpus;

    /**
     * @param ResourceGroupName $name The group name
     * @param list<CpuRange> $cpus The CPUs of VCPU; none when the option is absent
     * @param ThreadPriority|null $priority The THREAD_PRIORITY, when written
     * @param bool|null $enabled ENABLE (true) or DISABLE (false), when written
     * @param bool $force Whether FORCE is written
     */
    public function __construct(public readonly ResourceGroupName $name, array $cpus = [], public readonly ?ThreadPriority $priority = null, public readonly ?bool $enabled = null, public readonly bool $force = false, ?\SqlSemantics\Platform\MySql\Statement\StatementSemantics $statementInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        if ($statementInformation !== null) {
            $this->initializeStatementInformation($statementInformation);
        }
        $this->cpus = Check::listOf($cpus, CpuRange::class, 'VCPU lists CPU numbers and ranges.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Derives nothing: the statement names no relation and no expression.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $this->name->derive($derivation);
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALTER', 'RESOURCE', 'GROUP')->node($this->name);
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
