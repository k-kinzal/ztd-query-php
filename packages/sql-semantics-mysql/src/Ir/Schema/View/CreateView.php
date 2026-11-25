<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Schema\View;

use SqlSemantics\Platform\MySql\Ir\Ordering\RequestedResultAlias;
use SqlSemantics\Platform\MySql\Ir\Select;
use SqlSemantics\Platform\MySql\Ir\SelectSql;
use SqlSemantics\Platform\MySql\Model\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Identifier\NativeLocalNames;
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
     * The algorithm recorded in the catalog when this definition is installed.
     */
    public readonly ViewAlgorithm $installedAlgorithm;

    /**
     * Establishes the creation policy and checks the stored heading and complete body.
     * @param list<ViewColumn> $columns Checked names in output order
     * @param ViewAlgorithm $algorithm The requested algorithm, including requests that trigger a server fallback
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
        (new ViewQueryBounds())->check($query, $name);
        Check::input(count($columns) <= 4096, 'A MySQL view permits at most 4096 result columns.');
        $heading = Check::listOf($columns, ViewColumn::class, 'CREATE VIEW needs an owned nonempty heading.', 1);
        Check::input(count($heading) === count($query->columns), 'The view heading must match the query width.');
        (new ViewCatalogHeading())->check($heading, $query);
        $seen = [];
        $names = new NativeLocalNames();
        foreach ($heading as $column) {
            $key = $names->key($column->name->value);
            Check::input(!isset($seen[$key]), 'The view heading cannot repeat a column name.');
            $seen[$key] = true;
        }
        $this->columns = $heading;
        $this->installedAlgorithm = $algorithm === ViewAlgorithm::Merge && ($query->source === null || $query->distinct)
            ? ViewAlgorithm::Undefined : $algorithm;
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
        $target = $start . ' ALGORITHM=' . $this->algorithm->value . ' SQL SECURITY ' . $this->security->value . ' VIEW '
            . $preserve . $this->name->quoted();
        $renderer = new SelectSql();
        if ((new ViewCatalogHeading())->usesImplicit($this->columns, $this->query->release)) {
            $names = [];
            foreach ($this->columns as $position => $column) {
                $name = $this->query->columns[$position]->name;
                $names[] = $name instanceof RequestedResultAlias ? $name : $column->name;
            }

            return $target . ' AS ' . $renderer->render($this->query, viewNames: $names);
        }
        $names = [];
        foreach ($this->columns as $column) {
            $name = $column->name;
            $names[] = $name instanceof RequestedResultAlias ? $name->aliasSql() : $name->quoted();
        }

        return $target . ' (' . implode(', ', $names) . ') AS ' . $renderer->render($this->query, true, true);
    }
}
