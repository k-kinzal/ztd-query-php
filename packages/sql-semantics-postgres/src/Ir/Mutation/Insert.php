<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Mutation;

use SqlSemantics\Platform\PostgreSql\Ir\BooleanLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\TextLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\WideIntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\From\ColumnKind;
use SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Mutation\Insert\Destination;
use SqlSemantics\Platform\PostgreSql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * A complete literal INSERT into one declared base table.
 *
 * @visibility public
 * @example Publishing a checked literal insert
 *     $semantics = new \SqlSemantics\Platform\PostgreSql\Semantics();
 *     $value = $semantics->analyze('INSERT INTO public.t (n) VALUES (NULL)', $semantics->context(['CREATE TABLE public.t (n INTEGER)']));
 *     $value->toString() // => 'INSERT INTO "public"."t" ("n") VALUES (NULL)'
 */
final class Insert implements SemanticsIR
{
    use Snapshot;

    /**
     * @var non-empty-list<InsertRow> The ordered rows for this write
     */
    public readonly array $rows;

    /**
     * @var list<ReturnedColumn> The requested fields of the inserted row
     */
    public readonly array $returning;

    /**
     * Establishes the target and shape conditions before publishing this write.
     *
     * @param list<InsertRow> $rows
     * @param list<ReturnedColumn> $returning
     */
    public function __construct(
        public readonly Destination $target,
        array $rows,
        public readonly GrammarRelease $release = GrammarRelease::PostgreSql172,
        array $returning = [],
    ) {
        $width = count($target->written);
        $this->rows = Check::listOf($rows, InsertRow::class, 'An INSERT needs a nonempty list of rows.', 1);
        Check::input(count($this->rows) <= 512 && count($this->rows) * $width <= 8192, 'The supplied INSERT values exceed the profile limits.');
        foreach ($this->rows as $row) {
            Check::input(count($row->values) === $width, 'Every inserted row must match the target width.');
            foreach ($row->values as $index => $cell) {
                $value = $cell->value;
                $signed = $value instanceof IntegerLiteral || $value instanceof WideIntegerLiteral;
                $compatible = match ($target->written[$index]->kind) {
                    ColumnKind::Int4, ColumnKind::Int8 => $signed,
                    ColumnKind::Text => $value instanceof TextLiteral || $signed,
                    ColumnKind::Boolean => $value instanceof BooleanLiteral,
                };
                Check::input($value === null || $compatible, 'An inserted cell must match its target native kind.');
            }
        }
        $checked = Check::listOf($returning, ReturnedColumn::class, 'An INSERT needs checked RETURNING fields.');
        (new ReturningLayout())->check($target->relation, $checked);
        $this->returning = $checked;
    }

    /**
     * Identifies the pinned native language profile.
     */
    public function profileId(): string
    {
        return $this->release->value . '/utf8/standard-conforming-strings/native';
    }

    /**
     * Writes the absolute target and the explicit column and value order.
     */
    public function toString(): string
    {
        $rows = [];
        foreach ($this->rows as $row) {
            $rows[] = $row->toString();
        }

        return 'INSERT INTO ' . $this->target->toString() . ' VALUES ' . implode(', ', $rows)
            . (new ReturningSql())->clause($this->target->relation, $this->returning);
    }
}
