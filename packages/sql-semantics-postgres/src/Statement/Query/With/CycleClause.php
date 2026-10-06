<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Query\With;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * CYCLE columns SET mark [TO value DEFAULT value] USING path: cycle detection added to a recursive query.
 *
 * Mirrors PostgreSQL's `CTECycleClause`. The mark column and the path
 * column are appended to the columns of the common table. Without the TO and
 * DEFAULT values the mark is a boolean that is true on a cycle. The WITH
 * clause derives the values, which see no column.
 * Source: https://www.postgresql.org/docs/17/queries-with.html#QUERIES-WITH-CYCLE.
 *
 * @visibility public
 * @example Reading a cycle clause
 *     $clause = new \SqlSemantics\Platform\PostgreSql\Statement\Query\With\CycleClause([new \SqlSemantics\Statement\Identifier\Name('id')], new \SqlSemantics\Statement\Identifier\Name('is_cycle'), new \SqlSemantics\Statement\Identifier\Name('path'));
 *     [$clause->mark->value, $clause->path->value, $clause->markValue] // => ['is_cycle', 'path', null]
 * @example Refusing a mark value without its default
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Query\With\CycleClause([new \SqlSemantics\Statement\Identifier\Name('id')], new \SqlSemantics\Statement\Identifier\Name('m'), new \SqlSemantics\Statement\Identifier\Name('p'), new \SqlSemantics\Platform\PostgreSql\Statement\Literal\BooleanLiteral(true)) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class CycleClause implements Node
{
    use Snapshot;

    /**
     * @var non-empty-list<Name> The columns compared to detect a cycle
     */
    public readonly array $columns;

    /**
     * @param list<Name> $columns The columns compared to detect a cycle; at least one
     * @param Name $mark The name of the added mark column
     * @param Name $path The name of the added path column
     * @param Scalar|null $markValue The mark of a row on a cycle, written after TO
     * @param Scalar|null $markDefault The mark of every other row, written after DEFAULT
     */
    public function __construct(array $columns, public readonly Name $mark, public readonly Name $path, public readonly ?Scalar $markValue = null, public readonly ?Scalar $markDefault = null)
    {
        $this->columns = Check::listOf($columns, Name::class, 'A CYCLE clause names at least one column.', 1);
        Check::input(($markValue === null) === ($markDefault === null), 'A cycle mark value is written with its default.');
    }

    /**
     * Writes the clause.
     */
    public function render(Output $out): void
    {
        $out->keyword('CYCLE');
        foreach ($this->columns as $position => $column) {
            if ($position > 0) {
                $out->symbol(',');
            }
            $out->name($column, NameUse::Column);
        }
        $out->keyword('SET')->name($this->mark, NameUse::Column);
        if ($this->markValue !== null && $this->markDefault !== null) {
            $out->keyword('TO')->node($this->markValue)->keyword('DEFAULT')->node($this->markDefault);
        }
        $out->keyword('USING')->name($this->path, NameUse::Column);
    }
}
