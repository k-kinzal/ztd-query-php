<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Mutation\Insert;

use SqlSemantics\Platform\PostgreSql\Ir\From\ColumnKind;
use SqlSemantics\Platform\PostgreSql\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\PostgreSql\Ir\TableSource;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * The persistent INSERT relation and its ordered writable positions.
 *
 * @visibility public
 * @example Inserting into the second declared slot while leaving the first to its default
 *     $name = new \SqlSemantics\Platform\PostgreSql\Ir\Identifier('id');
 *     $stamp = new \SqlSemantics\Platform\PostgreSql\Ir\Identifier('stamp');
 *     $source = new \SqlSemantics\Platform\PostgreSql\Ir\TableSource(new \SqlSemantics\Platform\PostgreSql\Ir\Identifier('public'), new \SqlSemantics\Platform\PostgreSql\Ir\Identifier('events'), [$name, $stamp]);
 *     (new \SqlSemantics\Platform\PostgreSql\Ir\Mutation\Insert\Destination($source, [new \SqlSemantics\Platform\PostgreSql\Ir\Mutation\Insert\WriteSlot(1, \SqlSemantics\Platform\PostgreSql\Ir\From\ColumnKind::Int4)]))->toString() // => '"public"."events" ("stamp")'
 */
final class Destination
{
    use Snapshot;

    /**
     * @var non-empty-list<WriteSlot> The target slots in input order
     */
    public readonly array $written;

    /**
     * Checks the selected positions against the target relation's slot layout.
     *
     * @param list<WriteSlot> $written The target of each input position
     */
    public function __construct(public readonly TableSource $relation, array $written)
    {
        Check::input($relation->kind === ExternalRelationKind::BaseTable, 'An INSERT target must be a base table.');
        Check::input(count($written) >= 1 && count($written) <= 1024, 'An INSERT needs between one and 1024 target columns.');
        Check::input(!$relation->only, 'An INSERT target has no ONLY policy.');
        $checked = Check::listOf($written, WriteSlot::class, 'An INSERT needs checked target slots.', 1);
        $seen = [];
        foreach ($checked as $column) {
            Check::input($relation->columnKind($column->slot) === $column->kind, 'An INSERT write slot must belong to the target.');
            Check::input(!isset($seen[$column->slot]), 'An INSERT cannot repeat a write slot.');
            $seen[$column->slot] = true;
        }
        $this->written = $checked;
    }

    /**
     * Answers the native kind at a position in the written layout.
     */
    public function columnKind(int $position): ?ColumnKind
    {
        return $this->written[$position]->kind ?? null;
    }

    /**
     * Emits the absolute table and the write slots in the input order.
     */
    public function toString(): string
    {
        $columns = [];
        foreach ($this->written as $column) {
            $columns[] = $this->relation->columns[$column->slot]->quoted();
        }

        return $this->relation->schema->quoted() . '.' . $this->relation->table->quoted() . ' (' . implode(', ', $columns) . ')';
    }
}
