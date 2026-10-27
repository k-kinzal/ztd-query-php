<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Rendering\Output;

/**
 * `CREATE RESOURCE GROUP name TYPE = USER|SYSTEM [VCPU = …] [THREAD_PRIORITY = n] [ENABLE|DISABLE]` (8.0+).
 *
 * Rule: MYSQL-RESOURCE-GROUP-001. The structure retains the declared kind
 * and priority. Priority support depends on the execution environment: a
 * server without thread-priority support ignores even out-of-range values.
 * The `=` signs are optional words.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-resource-group.html. Status: Implemented.
 *
 * @visibility public
 * @example Creating a group bound to two CPUs
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('create resource group batch type user vcpu 2-3 thread_priority 10 disable')->toString() // => 'CREATE RESOURCE GROUP batch TYPE = USER VCPU = 2 - 3 THREAD_PRIORITY = 10 DISABLE'
 */
final class CreateResourceGroup extends \SqlSemantics\Platform\MySql\Statement\SqlStatement implements Statement, \SqlSemantics\Statement\AnalyzedStatement
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @var list<CpuRange> The CPUs of VCPU; none when the option is absent
     */
    public readonly array $cpus;

    /**
     * @param ResourceGroupName $name The group name
     * @param ResourceGroupKind $kind The group type
     * @param list<CpuRange> $cpus The CPUs of VCPU; none when the option is absent
     * @param ThreadPriority|null $priority The THREAD_PRIORITY, when written
     * @param bool|null $enabled ENABLE (true) or DISABLE (false), when written
     */
    public function __construct(public readonly ResourceGroupName $name, public readonly ResourceGroupKind $kind, array $cpus = [], public readonly ?ThreadPriority $priority = null, public readonly ?bool $enabled = null, ?\SqlSemantics\Platform\MySql\Statement\StatementSemantics $statementInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null)
    {
        if ($statementInformation !== null) {
            $this->initializeStatementInformation($statementInformation);
        }
        $this->cpus = Check::listOf($cpus, CpuRange::class, 'VCPU lists CPU numbers and ranges.');
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Reports the name conversion retained by the operand.
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
        $out->keyword('CREATE', 'RESOURCE', 'GROUP')->node($this->name)->keyword('TYPE')->symbol('=')->keyword($this->kind->value);
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
