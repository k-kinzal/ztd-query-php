<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\Alter;

use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * A complete left-to-right sequence of persistent MySQL table renames.
 *
 * @visibility public
 * @example Publishing a qualified table move
 *     $semantics = new \SqlSemantics\Platform\MySql\Semantics();
 *     $declarations = $semantics->context(['CREATE TABLE reports.entries (id integer)']);
 *     $semantics->analyze('RENAME TABLE reports.entries TO reports.archived', $declarations)->toString() // => 'RENAME TABLE `reports`.`entries` TO `reports`.`archived`'
 */
final class RenameTable implements SemanticsIR
{
    use Snapshot;

    /**
     * @var non-empty-list<TableRename> Absolute moves in native execution order
     */
    public readonly array $moves;

    /**
     * Establishes each preceding transition before publishing the complete statement.
     *
     * @param list<TableRename> $moves The ordered name and origin roles
     */
    public function __construct(
        array $moves,
        public readonly GrammarRelease $release = GrammarRelease::MySql847,
    ) {
        $checked = Check::listOf($moves, TableRename::class, 'RENAME TABLE needs a nonempty ordered sequence.', 1);
        $visible = [];
        foreach ($checked as $move) {
            $from = $move->from->quoted();
            $to = $move->to->quoted();
            $origin = array_key_exists($from, $visible) ? $visible[$from] : $move->from;
            Check::input($origin !== null, 'A vacated source must have been recreated by an earlier move.');
            Check::input($origin->quoted() === $move->origin->quoted(), 'A rename move must retain its originating table identity.');
            Check::input(!isset($visible[$to]), 'A rename destination cannot replace a table moved there by an earlier step.');
            $visible[$from] = null;
            $visible[$to] = $origin;
        }
        $this->moves = $checked;
    }

    /**
     * Identifies the fixed native MySQL naming and lexical profile.
     */
    public function profileId(): string
    {
        return $this->release->value . '/utf8mb4/lower-case-table-names=0/sql-mode=empty/native';
    }

    /**
     * Emits one native renaming statement in the checked execution order.
     */
    public function toString(): string
    {
        $moves = [];
        foreach ($this->moves as $move) {
            $moves[] = $move->toString();
        }

        return 'RENAME TABLE ' . implode(', ', $moves);
    }
}
