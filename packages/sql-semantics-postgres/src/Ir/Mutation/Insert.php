<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Mutation;

use SqlSemantics\Platform\PostgreSql\Ir\TableSource;
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
     * Establishes the target and shape conditions before publishing this write.
     *
     * @param list<InsertRow> $rows
     */
    public function __construct(
        public readonly TableSource $target,
        array $rows,
        public readonly GrammarRelease $release = GrammarRelease::PostgreSql172,
    ) {
        $width = count($target->columns);
        Check::input($width >= 1 && $width <= 1024, 'An INSERT needs between one and 1024 target columns.');
        Check::input(!$target->only, 'An INSERT target has no ONLY policy.');
        $this->rows = Check::listOf($rows, InsertRow::class, 'An INSERT needs a nonempty list of rows.', 1);
        Check::input(count($this->rows) <= 512 && count($this->rows) * $width <= 8192, 'The supplied INSERT values exceed the profile limits.');
        foreach ($this->rows as $row) {
            Check::input(count($row->values) === $width, 'Every inserted row must match the target width.');
        }
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
        $target = $this->target;
        $columns = [];
        foreach ($target->columns as $column) {
            $columns[] = $column->quoted();
        }
        $rows = [];
        foreach ($this->rows as $row) {
            $rows[] = $row->toString();
        }

        return 'INSERT INTO ' . $target->schema->quoted() . '.' . $target->table->quoted()
            . ' (' . implode(', ', $columns) . ') VALUES ' . implode(', ', $rows);
    }
}
