<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Trigger;

use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Node;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\StringConstant;

/**
 * One WHEN item of an event trigger: a filter variable and the values it must have.
 *
 * The server accepts the variable `tag` with command tags, and reports other variables.
 * Source: https://www.postgresql.org/docs/17/sql-createeventtrigger.html.
 *
 * @visibility SqlSemantics
 */
final class EventFilter implements Node
{
    use Snapshot;

    /**
     * @var non-empty-list<StringConstant> The accepted values
     */
    public readonly array $values;

    /**
     * @param Name $variable The filter variable
     * @param list<StringConstant> $values The accepted values
     */
    public function __construct(public readonly Name $variable, array $values)
    {
        $this->values = Check::listOf($values, StringConstant::class, 'A filter accepts at least one string.', 1);
    }

    /**
     * Writes the filter.
     */
    public function render(Output $out): void
    {
        $out->name($this->variable)->keyword('IN')->symbol('(')->list($this->values)->symbol(')');
    }
}
