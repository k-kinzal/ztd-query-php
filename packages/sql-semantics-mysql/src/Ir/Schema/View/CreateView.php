<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\View;

use SqlSemantics\Platform\MySql\Ir\Select;
use SqlSemantics\Platform\MySql\Ir\SelectSql;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * A complete native persistent view declaration.
 *
 * @visibility public
 * @example Publishing a view definition
 *     $semantics = new \SqlSemantics\Platform\MySql\Semantics();
 *     $ir = $semantics->analyze('CREATE VIEW app.answer AS SELECT 42 AS value');
 *     $ir instanceof \SqlSemantics\Platform\MySql\Ir\Schema\View\CreateView // => true
 */
final class CreateView implements SemanticsIR
{
    use Snapshot;

    /**
     * @var non-empty-list<ViewColumn> The exact ordered names installed in the catalog
     */
    public readonly array $columns;

    /**
     * Establishes the creation policy and checks the stored heading and complete body.
     * @param list<ViewColumn> $columns Checked names in output order
     */
    public function __construct(
        public readonly ViewName $name,
        public readonly Select $query,
        array $columns,
        public readonly ViewCreationPolicy $policy = ViewCreationPolicy::RequireNew,
        public readonly ViewAlgorithm $algorithm = ViewAlgorithm::Undefined,
        public readonly ViewSecurity $security = ViewSecurity::Definer,
    ) {
        Check::input(
            $policy !== ViewCreationPolicy::Preserve || $query->release === GrammarRelease::MySql910,
            'CREATE VIEW IF NOT EXISTS requires MySQL 9.1.'
        );
        (new ViewQueryBounds())->check($query, $name, $algorithm);
        $heading = Check::listOf($columns, ViewColumn::class, 'CREATE VIEW needs an owned nonempty heading.', 1);
        Check::input(count($heading) === count($query->columns), 'The view heading must match the query width.');
        $seen = [];
        foreach ($heading as $column) {
            $key = $column->name->key();
            Check::input(!isset($seen[$key]), 'The view heading cannot repeat a column name.');
            $seen[$key] = true;
        }
        $this->columns = $heading;
    }

    /**
     * Identifies the exact native profile shared with the stored query.
     */
    public function profileId(): string
    {
        return $this->query->release->value . '/utf8mb4/lower-case-table-names=0/sql-mode=empty/native/view-definition';
    }

    /**
     * Emits the native effect and the checked scope.
     */
    public function toString(): string
    {
        $start = $this->policy === ViewCreationPolicy::Replace ? 'CREATE OR REPLACE' : 'CREATE';
        $preserve = $this->policy === ViewCreationPolicy::Preserve ? 'IF NOT EXISTS ' : '';
        $names = [];
        foreach ($this->columns as $column) {
            $names[] = $column->name->quoted();
        }

        return $start . ' ALGORITHM=' . $this->algorithm->value . ' SQL SECURITY ' . $this->security->value . ' VIEW '
            . $preserve . $this->name->quoted() . ' (' . implode(', ', $names) . ') AS ' . (new SelectSql())->render($this->query, true, true);
    }
}
