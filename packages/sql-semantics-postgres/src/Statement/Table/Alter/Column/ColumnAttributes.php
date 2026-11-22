<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\Column;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Command\Alterations;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Writing;
use SqlSemantics\Platform\PostgreSql\Statement\Option\Definition;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Alter\AlterCommand;

/**
 * ALTER [ COLUMN ] ... SET ( ... ) or RESET ( ... ): the per-column options.
 *
 * Mirrors `AT_SetOptions` and `AT_ResetOptions`.
 * Source: https://www.postgresql.org/docs/17/sql-altertable.html.
 *
 * @visibility SqlSemantics
 */
final class ColumnAttributes implements AlterCommand
{
    use Snapshot;

    /**
     * @var non-empty-list<Definition> The options
     */
    public readonly array $options;

    /**
     * @param Name $column The column
     * @param bool $reset Whether the options are reset; set otherwise
     * @param list<Definition> $options The options
     */
    public function __construct(public readonly Name $column, public readonly bool $reset, array $options)
    {
        $this->options = Check::listOf($options, Definition::class, 'Column options are definitions.', 1);
    }

    /**
     * Checks that the column exists and derives the options.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        (new Alterations())->column($derivation, $environment, $this->column);
        foreach ($this->options as $option) {
            $option->deriveClause($derivation, $environment);
        }
    }

    /**
     * Writes the action.
     */
    public function render(Output $out): void
    {
        $out->keyword('ALTER')->name($this->column)->keyword($this->reset ? 'RESET' : 'SET');
        (new Writing())->definitions($out, $this->options);
    }
}
