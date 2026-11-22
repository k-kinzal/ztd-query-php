<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Alter\Command;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Resolution\Environment;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Rules\TableDefinition\ElementFacts;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterCommand;
use SqlSemantics\Platform\MySql\Statement\Table\TableOption;

/**
 * Table options written one after the other without commas, such as `ENGINE = InnoDB COMMENT = 'x'`.
 *
 * The server adds each option of the run to the action list of ALTER TABLE
 * (PT_create_table_option); options separated by commas are separate runs.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-table.html#alter-table-options.
 *
 * @visibility SqlSemantics
 */
final class SetTableOptions implements AlterCommand
{
    use Snapshot;

    /**
     * @var list<TableOption> The options in order; at least one
     */
    public readonly array $options;

    /**
     * @param list<TableOption> $options The options in order; at least one
     */
    public function __construct(array $options)
    {
        Check::input($options !== [], 'A run of table options holds at least one option.');
        $this->options = Check::listOf($options, TableOption::class, 'A run of table options is a list of table options.');
    }

    /**
     * Derives the options.
     */
    public function deriveCommand(Derivation $derivation, Environment $scope): void
    {
        (new ElementFacts())->options($this->options, $derivation);
    }

    /**
     * Writes the options.
     */
    public function render(Output $out): void
    {
        foreach ($this->options as $option) {
            $out->node($option);
        }
    }
}
