<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Account\NumberChecks;
use SqlSemantics\Platform\MySql\Statement\Literal\Numeral;
use SqlSemantics\Platform\MySql\Statement\Statement;
use SqlSemantics\Rendering\Output;

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
 *     (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('set resource group batch for 14 15')->toString() // => 'SET RESOURCE GROUP batch FOR 14 15'
 */
final class SetResourceGroup extends \SqlSemantics\Platform\MySql\Statement\SqlStatement implements Statement, \SqlSemantics\Statement\AnalyzedStatement
{
    use \SqlSemantics\Statement\Semantics\Occurrence;

    /**
     * @var list<bool> The retained comma at each boundary between threads
     */
    public readonly array $threadsCommas;

    /**
     * @var list<Numeral> The thread ids of FOR; none for the session's thread
     */
    public readonly array $threads;

    /**
     * @param ResourceGroupName $name The group name
     * @param list<Numeral> $threads The thread ids of FOR; none for the session's thread
     * @param list<bool>|null $threadsCommas Optional commas between adjacent threads
     */
    public function __construct(public readonly ResourceGroupName $name, array $threads = [], ?\SqlSemantics\Platform\MySql\Statement\StatementSemantics $statementInformation = null, public readonly ?\SqlSemantics\Statement\Source\Span $inputSource = null, ?array $threadsCommas = null)
    {
        if ($statementInformation !== null) {
            $this->initializeStatementInformation($statementInformation);
        }
        $this->threads = Check::listOf($threads, Numeral::class, 'FOR lists thread ids.');
        $this->threadsCommas = (new \SqlSemantics\Platform\MySql\Rules\Rendering\OptionalCommas())->checked($threadsCommas, count($this->threads), true);
        (new \SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations())->check($this);
    }

    /**
     * Reports a thread id that is no integer.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        $this->name->derive($derivation);
        foreach ($this->threads as $thread) {
            (new NumberChecks())->range($derivation, $thread, 'thread id', '0', null);
        }
    }

    /**
     * Writes the request.
     */
    public function render(Output $out): void
    {
        $out->keyword('SET', 'RESOURCE', 'GROUP')->node($this->name);
        if ($this->threads !== []) {
            $out->keyword('FOR');
            (new \SqlSemantics\Platform\MySql\Rules\Rendering\OptionalCommas())->render($out, $this->threads, $this->threadsCommas);
        }
    }
}
