<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Schema\Query;

use SqlSemantics\Platform\PostgreSql\Ir\Identifier;
use SqlSemantics\Platform\PostgreSql\Ir\Rows\Values\ValuesQuery;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\TableName;
use SqlSemantics\Platform\PostgreSql\Ir\Select;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * An ordinary persistent heap table with a query-defined heading and optional initial data.
 *
 * @visibility public
 * @example Reading the effective name of a column in a new table
 *     (new \SqlSemantics\Platform\PostgreSql\Semantics())->analyze('CREATE TABLE snapshot AS SELECT 7 AS copied')->columns[0]->value // => 'copied'
 */
final class CreateTableAs implements SemanticsIR
{
    use Snapshot;

    /**
     * @var non-empty-list<Identifier> The full catalog heading in output order
     */
    public readonly array $columns;

    /**
     * Closes the heading, definition and storage policy over a persistent target.
     *
     * @param list<Identifier> $columns The complete effective heading
     */
    public function __construct(
        public readonly TableName $target,
        public readonly Select|ValuesQuery $query,
        array $columns,
        public readonly bool $unlogged = false,
        public readonly bool $ifNotExists = false,
        public readonly bool $withData = true,
    ) {
        $heading = Check::listOf($columns, Identifier::class, 'CREATE TABLE AS needs a nonempty ordered heading.', 1);
        $width = (new CreateAsBounds())->width($query);
        Check::input(count($heading) === $width && $width <= 1600, 'The new table heading must match its query within 1600 columns.');
        $seen = [];
        foreach ($heading as $column) {
            Check::input(!isset($seen[$column->value]), 'CREATE TABLE AS cannot repeat a column name.');
            $seen[$column->value] = true;
        }
        $this->columns = $heading;
    }

    /**
     * Identifies the selected native query profile.
     */
    public function profileId(): string
    {
        return $this->query->profileId();
    }

    /**
     * Emits the complete table creation and initial data policy.
     */
    public function toString(): string
    {
        $columns = [];
        foreach ($this->columns as $column) {
            $columns[] = $column->quoted();
        }

        return 'CREATE ' . ($this->unlogged ? 'UNLOGGED ' : '') . 'TABLE ' . ($this->ifNotExists ? 'IF NOT EXISTS ' : '')
            . $this->target->quoted() . ' (' . implode(', ', $columns) . ') USING heap AS ' . $this->query->toString()
            . ($this->withData ? ' WITH DATA' : ' WITH NO DATA');
    }
}
