<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Account\NumberChecks;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * `SET RESOURCE GROUP name [FOR thread_id, …]` (8.0+): a request to assign threads, or the session's thread, to a resource group.
 *
 * Mirrors PT_set_resource_group. Rule: MYSQL-RESOURCE-GROUP-001. A thread id
 * written as a decimal or floating number is reported
 * (MYSQL-ACCOUNT-NUMBER-001).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/set-resource-group.html. Status: Implemented.
 *
 * @visibility public
 * @example Assigning two threads
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('set resource group batch for 14 15')->toString() // => 'SET RESOURCE GROUP batch FOR 14, 15'
 */
final class SetResourceGroup implements Statement
{
    use Snapshot;

    /**
     * @var list<Numeral> The thread ids of FOR; none for the session's thread
     */
    public readonly array $threads;

    /**
     * @param Name $name The group name
     * @param list<Numeral> $threads The thread ids of FOR; none for the session's thread
     */
    public function __construct(public readonly Name $name, array $threads = [])
    {
        $this->threads = Check::listOf($threads, Numeral::class, 'FOR lists thread ids.');
    }

    /**
     * Reports a thread id that is no integer.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        foreach ($this->threads as $thread) {
            (new NumberChecks())->range($derivation, $thread, 'thread id', '0', null);
        }
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('SET', 'RESOURCE', 'GROUP')->name($this->name, NameUse::Label);
        if ($this->threads !== []) {
            $out->keyword('FOR')->list($this->threads);
        }
    }
}
