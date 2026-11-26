<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\Query;

use SqlSemantics\Platform\MySql\Ir\Binding\ProjectionMode;
use SqlSemantics\Platform\MySql\Ir\From\ExternalRelationKind;
use SqlSemantics\Platform\MySql\Ir\Schema\Identity\TableIdentity;
use SqlSemantics\Platform\MySql\Ir\Schema\TableColumn;
use SqlSemantics\Platform\MySql\Ir\Select;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * Creates and populates a native table from a checked native projection.
 *
 * @visibility public
 * @example Reading the projected column name in a temporary table declaration
 *     $semantics = new \SqlSemantics\Platform\MySql\Semantics();
 *     $value = $semantics->analyze('CREATE TEMPORARY TABLE app.report AS SELECT 42 AS answer');
 *     [$value->target->kind->value, $value->query->columns[0]->name->value] // => ['temporary-table', 'answer']
 */
final class CreateTableAs implements SemanticsIR
{
    use Snapshot;

    /**
     * @var list<TableColumn> The explicitly declared storage and defaults in declaration order
     */
    public readonly array $declaredColumns;

    /**
     * Checks both contributions to the final native table heading.
     *
     * @param list<TableColumn> $declaredColumns The explicit column definitions
     */
    public function __construct(
        public readonly TableIdentity $target,
        public readonly Select $query,
        public readonly bool $ifNotExists = false,
        array $declaredColumns = [],
    ) {
        Check::input($query->mode === ProjectionMode::Empty, 'CREATE TABLE AS requires empty sql_mode.');
        $columns = Check::listOf($declaredColumns, TableColumn::class, 'CREATE TABLE AS requires an ordered list of explicit columns.');
        $layout = new CreateAsLayout();
        $layout->query($query);
        $layout->declaration($query, $columns);
        $this->declaredColumns = $columns;
    }

    /**
     * Identifies the fixed native release and storage role.
     */
    public function profileId(): string
    {
        return $this->query->release->value . '/utf8mb4/lower-case-table-names=0/sql-mode=empty/native/innodb';
    }

    /**
     * Writes the destination followed by the checked query with its exact heading.
     */
    public function toString(): string
    {
        $columns = [];
        foreach ($this->declaredColumns as $column) {
            $columns[] = $column->toString();
        }

        return 'CREATE ' . ($this->target->kind === ExternalRelationKind::TemporaryTable ? 'TEMPORARY ' : '')
            . 'TABLE ' . ($this->ifNotExists ? 'IF NOT EXISTS ' : '') . $this->target->quoted()
            . ($columns === [] ? '' : ' (' . implode(', ', $columns) . ')')
            . ' ENGINE=InnoDB AS ' . $this->query->toString();
    }
}
