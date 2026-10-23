<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Hint\Form;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Hint\HintForm;
use SqlSemantics\Platform\MySql\Statement\Hint\HintName;
use SqlSemantics\Platform\MySql\Statement\Hint\OptimizerHint;
use SqlSemantics\Statement\Snapshot;

/**
 * A hint of the table and join order forms: an optional query block and a list of tables.
 *
 * The table-level hints (BKA, BNL, HASH_JOIN, MERGE,
 * DERIVED_CONDITION_PUSHDOWN and their NO_ forms) apply to the tables they
 * name, or to every table of the query block when they name none.
 * JOIN_ORDER, JOIN_PREFIX and JOIN_SUFFIX order the tables they name, and
 * JOIN_FIXED_ORDER names none. A table names its own query block only when
 * the hint writes no leading one. Source:
 * https://dev.mysql.com/doc/refman/8.4/en/optimizer-hints.html#optimizer-hints-table-level,
 * https://dev.mysql.com/doc/refman/8.4/en/optimizer-hints.html#optimizer-hints-join-order.
 *
 * @visibility public
 * @example Writing a table-level hint
 *     (new \SqlSemantics\Platform\MySql\Statement\Hint\Form\TableHint(\SqlSemantics\Platform\MySql\Statement\Hint\HintName::NoBka, null, [new \SqlSemantics\Platform\MySql\Statement\Hint\Form\HintTable('t1'), new \SqlSemantics\Platform\MySql\Statement\Hint\Form\HintTable('t2', 'qb')]))->text() // => 'NO_BKA(`t1`, `t2`@`qb`)'
 */
final class TableHint implements OptimizerHint
{
    use Snapshot;

    /**
     * @var list<HintTable> The tables, in written order
     */
    public readonly array $tables;

    /**
     * @param HintName $hint The name of the hint
     * @param string|null $block The query block written after `@` before the tables, or null for the block of the hint
     * @param list<HintTable> $tables The tables, in written order
     */
    public function __construct(public readonly HintName $hint, public readonly ?string $block, array $tables)
    {
        $form = $hint->form();
        Check::input($form === HintForm::Table || $form === HintForm::JoinOrder || $form === HintForm::FixedOrder, 'A table hint is a table-level or join order hint.');
        $this->tables = Check::listOf($tables, HintTable::class, 'A table hint names tables.');
        Check::input($form !== HintForm::FixedOrder || $this->tables === [], 'JOIN_FIXED_ORDER names no table.');
        Check::input($block !== '', 'A query block has a name.');
        foreach ($this->tables as $table) {
            Check::input($block === null || $table->block === null, 'A table names its query block only when the hint names none.');
        }
    }

    /**
     * Answers the name of the hint.
     */
    public function name(): HintName
    {
        return $this->hint;
    }

    /**
     * Answers the hint as it is written in a hint comment, with every name quoted.
     */
    public function text(): string
    {
        $parts = array_map(static fn (HintTable $table): string => $table->text(), $this->tables);
        $head = $this->block === null ? '' : '@' . HintTable::quote($this->block) . ($parts === [] ? '' : ' ');

        return $this->hint->value . '(' . $head . implode(', ', $parts) . ')';
    }
}
