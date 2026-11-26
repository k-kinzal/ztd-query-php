<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Rows\Values;

use SqlSemantics\Platform\PostgreSql\Ir\From\ColumnKind;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * A complete standalone PostgreSQL VALUES table.
 *
 * @visibility public
 * @example Rendering two constant rows
 *     $sql = new \SqlSemantics\Platform\PostgreSql\Semantics();
 *     $sql->analyze('VALUES (1), (2)')->toString() // => 'VALUES (CAST(1 AS pg_catalog.int4)), (CAST(2 AS pg_catalog.int4))'
 */
final class ValuesQuery implements SemanticsIR
{
    use Snapshot;

    /**
     * @var non-empty-list<ValuesRow> Rows in statement order
     */
    public readonly array $rows;

    /**
     * Checks the rectangular row layout and every compatible native result kind.
     * @param list<ValuesRow> $rows The ordered rows
     */
    public function __construct(array $rows, public readonly GrammarRelease $release = GrammarRelease::PostgreSql172)
    {
        $checked = Check::listOf($rows, ValuesRow::class, 'VALUES needs a nonempty list of rows.', 1);
        $width = count($checked[0]->cells);
        foreach ($checked as $row) {
            Check::input(count($row->cells) === $width, 'VALUES rows must have equal widths.');
        }
        $types = new ColumnUnification();
        for ($column = 0; $column < $width; ++$column) {
            $types->of($checked, $column);
        }
        $this->rows = $checked;
    }

    /**
     * Answers the native result kind at a zero-based column position.
     */
    public function columnKind(int $column): ColumnKind
    {
        Check::input(isset($this->rows[0]->cells[$column]), 'VALUES has no such output column.');

        return (new ColumnUnification())->of($this->rows, $column);
    }

    /**
     * Identifies the fixed native release and lexical settings.
     */
    public function profileId(): string
    {
        return $this->release->value . '/utf8/standard-conforming-strings/native';
    }

    /**
     * Emits a single native VALUES operation.
     */
    public function toString(): string
    {
        $sql = [];
        foreach ($this->rows as $row) {
            $sql[] = $row->toString();
        }

        return 'VALUES ' . implode(', ', $sql);
    }
}
