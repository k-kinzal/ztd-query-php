<?php

declare(strict_types=1);

namespace MySqlMemory\Hint;

use SqlSemantics\Platform\MySql\Statement\Hint\Form\BlockNameHint;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\ExecutionTimeHint;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\HintLiteralKind;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\HintTable;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\KeyHint;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\StrategyHint;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\TableHint;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\VariableHint;

/**
 * Writes optimizer hints and their names as the server writes them in the warnings about them.
 *
 * Names are quoted with backticks, or double quotes under ANSI_QUOTES. A table-level hint is
 * written for one table, or for its block when it names none; MRR, NO_MRR, NO_ICP and
 * NO_RANGE_OPTIMIZATION for one index; the other index-level hints with all their indexes. The
 * spacing is the server's own, such as `BKA(`t` )`, `INDEX(`t`  `i`, `j`)`, `JOIN_ORDER( `t`,`u`)`
 * and `NO_SEMIJOIN(  FIRSTMATCH)`; a SET_VAR value is written as a number, as a quoted string,
 * or as nothing for a decimal (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/optimizer-hints.html.
 *
 * @visibility MySqlMemory\Hint
 */
final class Printer
{
    /**
     * @param bool $ansiQuotes Whether names are quoted with double quotes (ANSI_QUOTES)
     */
    public function __construct(public readonly bool $ansiQuotes = false)
    {
    }

    /**
     * Answers a name between quotes, each quote in it doubled.
     */
    public function quote(string $name): string
    {
        $quote = $this->ansiQuotes ? '"' : '`';

        return $quote . str_replace($quote, $quote . $quote, $name) . $quote;
    }

    /**
     * Answers a table of a hint with the block it names, if any.
     */
    public function table(HintTable $table, ?string $block = null): string
    {
        $named = $block ?? $table->block;

        return $this->quote($table->name) . ($named === null ? '' : '@' . $this->quote($named));
    }

    /**
     * Answers a table-level hint for one of its tables, or for its block when the table is null.
     */
    public function level(TableHint $hint, ?HintTable $table): string
    {
        $inner = $table === null ? ($hint->block === null ? '' : '@' . $this->quote($hint->block)) : $this->table($table);

        return $hint->hint->value . '(' . $inner . ' )';
    }

    /**
     * Answers a join order hint, or JOIN_FIXED_ORDER.
     */
    public function order(TableHint $hint): string
    {
        $tables = array_map(fn (HintTable $table): string => $this->table($table), $hint->tables);

        return $hint->hint->value . '(' . ($hint->block === null ? '' : '@' . $this->quote($hint->block)) . ' ' . implode(',', $tables) . ')';
    }

    /**
     * Answers MRR, NO_MRR, NO_ICP or NO_RANGE_OPTIMIZATION for one of its indexes, or for its table when the index is null.
     */
    public function index(KeyHint $hint, ?string $index): string
    {
        return $hint->hint->value . '(' . $this->table($hint->table, $hint->block) . ' ' . ($index === null ? '' : $this->quote($index) . ' ') . ')';
    }

    /**
     * Answers an index-level hint with all its indexes.
     */
    public function key(KeyHint $hint): string
    {
        $indexes = array_map($this->quote(...), $hint->indexes);

        return $hint->hint->value . '(' . $this->table($hint->table, $hint->block) . ' ' . ($indexes === [] ? '' : ' ' . implode(', ', $indexes)) . ')';
    }

    /**
     * Answers a subquery hint.
     */
    public function strategy(StrategyHint $hint): string
    {
        return $hint->hint->value . '(' . ($hint->block === null ? '' : '@' . $this->quote($hint->block)) . ' ' . ($hint->strategies === [] ? '' : ' ' . implode(', ', $hint->strategies)) . ')';
    }

    /**
     * Answers a SET_VAR hint, with the name of the variable it sets.
     */
    public function variable(VariableHint $hint, string $name): string
    {
        $value = match ($hint->value->kind) {
            HintLiteralKind::Integer => $hint->value->value,
            HintLiteralKind::Decimal => '',
            HintLiteralKind::Word, HintLiteralKind::Text => "'" . $hint->value->value . "'",
        };

        return 'SET_VAR(' . $name . '=' . $value . ') ';
    }

    /**
     * Answers MAX_EXECUTION_TIME.
     */
    public function time(ExecutionTimeHint $hint): string
    {
        return $hint->text();
    }

    /**
     * Answers QB_NAME.
     */
    public function block(BlockNameHint $hint): string
    {
        return 'QB_NAME(' . $this->quote($hint->block) . ')';
    }
}
