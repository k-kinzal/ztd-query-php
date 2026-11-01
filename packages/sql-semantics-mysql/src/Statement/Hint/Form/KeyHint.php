<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Hint\Form;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Hint\HintForm;
use SqlSemantics\Platform\MySql\Statement\Hint\HintName;
use SqlSemantics\Platform\MySql\Statement\Hint\OptimizerHint;
use SqlSemantics\Statement\Snapshot;

/**
 * A hint of the index form: an optional query block, one table and the indexes of the table it names.
 *
 * The index-level hints (INDEX, JOIN_INDEX, GROUP_INDEX, ORDER_INDEX,
 * INDEX_MERGE, SKIP_SCAN, MRR, NO_ICP, NO_RANGE_OPTIMIZATION and their NO_
 * forms) apply to the indexes they name, or to every index of the table
 * when they name none. PRIMARY names the primary key. Source:
 * https://dev.mysql.com/doc/refman/8.4/en/optimizer-hints.html#optimizer-hints-index-level.
 *
 * @visibility public
 * @example Writing an index-level hint
 *     (new \SqlSemantics\Platform\MySql\Statement\Hint\Form\KeyHint(\SqlSemantics\Platform\MySql\Statement\Hint\HintName::Index, 'qb', new \SqlSemantics\Platform\MySql\Statement\Hint\Form\HintTable('t1'), ['i1', 'PRIMARY']))->text() // => 'INDEX(@`qb` `t1` `i1`, `PRIMARY`)'
 */
final class KeyHint implements OptimizerHint
{
    use Snapshot;

    /**
     * @var list<string> The indexes, in written order
     */
    public readonly array $indexes;

    /**
     * @param HintName $hint The name of the hint
     * @param string|null $block The query block written after `@` before the table, or null for the block of the hint
     * @param HintTable $table The table
     * @param list<string> $indexes The indexes, in written order
     */
    public function __construct(public readonly HintName $hint, public readonly ?string $block, public readonly HintTable $table, array $indexes)
    {
        Check::input($hint->form() === HintForm::Key, 'A key hint is an index-level hint.');
        $list = [];
        foreach ($indexes as $index) {
            Check::input($index !== '', 'An index of a hint has a name.');
            $list[] = $index;
        }
        $this->indexes = $list;
        Check::input($block !== '', 'A query block has a name.');
        Check::input($block === null || $table->block === null, 'A table names its query block only when the hint names none.');
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
        $head = $this->block === null ? '' : '@' . HintTable::quote($this->block) . ' ';
        $indexes = array_map(HintTable::quote(...), $this->indexes);

        return $this->hint->value . '(' . $head . $this->table->text() . ($indexes === [] ? '' : ' ' . implode(', ', $indexes)) . ')';
    }
}
