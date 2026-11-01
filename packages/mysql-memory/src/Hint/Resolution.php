<?php

declare(strict_types=1);

namespace MySqlMemory\Hint;

use MySqlMemory\Error\Family\StatementError;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\KeyHint;
use SqlSemantics\Platform\MySql\Statement\Hint\Form\TableHint;
use SqlSemantics\Platform\MySql\Statement\Hint\HintForm;

/**
 * Resolves the tables and indexes the hints that count name, as the server does when it sets up the tables of each query block: a warning for each name it does not find.
 *
 * The blocks are resolved in the order Blocks::$resolved gives. In each block the tables are
 * looked up first, in the order the hints were read: a table of a table-level hint, of a join
 * order hint (written without its block in the warning, and not looked up when it names its
 * block), of MRR, NO_MRR, NO_ICP or NO_RANGE_OPTIMIZATION without an index, and of the other
 * index-level hints. Then the indexes: each index of an index-level hint whose table is unknown
 * or has no index of the name, compared without regard to case, warns ER_UNRESOLVED_HINT_NAME
 * (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/optimizer-hints.html.
 *
 * @visibility MySqlMemory\Hint
 */
final class Resolution
{
    /**
     * @var list<array{int, string}> The warnings, each its error number and message, in order
     */
    public array $warnings = [];

    /**
     * @param Registration $registration The hints that count
     * @param Printer $printer How names are written in the warnings
     */
    public function __construct(public readonly Registration $registration, public readonly Printer $printer)
    {
    }

    /**
     * Resolves the names of every block.
     */
    public function resolve(): self
    {
        $blocks = $this->registration->blocks;
        foreach ($blocks->resolved as $number) {
            $block = $blocks->blocks[$number];
            $accepted = array_values(array_filter($this->registration->accepted, static fn (array $entry): bool => $entry[0] === $number));
            foreach ($accepted as [, $hint, $table, $index]) {
                if ($table === null || $index !== null || $block->table($table->name) !== null) {
                    continue;
                }
                $ordered = $hint instanceof TableHint && $hint->hint->form() !== HintForm::Table;
                $this->warn($this->printer->quote($table->name) . ($ordered ? '' : '@' . $this->printer->quote($block->label())), $hint->name()->value);
            }
            foreach ($accepted as [, $hint, $table, $index]) {
                if (!$hint instanceof KeyHint || $table === null) {
                    continue;
                }
                $known = array_map(mb_strtolower(...), $block->table($table->name) ?? []);
                $named = $index === null ? (in_array(Registration::kind($hint->hint), Registration::SPLIT, true) ? [] : $hint->indexes) : [$index];
                foreach ($named as $name) {
                    if (!in_array(mb_strtolower($name), $known, true)) {
                        $this->warn($this->printer->quote($table->name) . '@' . $this->printer->quote($block->label()) . ' ' . $this->printer->quote($name), $hint->hint->value);
                    }
                }
            }
        }

        return $this;
    }

    /**
     * Records the warning about a name a hint does not resolve.
     */
    public function warn(string $name, string $hint): void
    {
        $this->warnings[] = [StatementError::HintUnresolvedName->number(), StatementError::HintUnresolvedName->message($name, $hint)];
    }
}
